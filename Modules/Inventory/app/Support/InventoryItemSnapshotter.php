<?php

namespace Modules\Inventory\Support;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

/**
 * Writes a day's inventory snapshot — the stored item columns, the computed
 * stock metrics, and the report's group figures — into inventory_item_snapshots.
 *
 * Extracted from the scheduled command because the snapshot is no longer only a
 * history sidecar: the items list reads it, so an edit that changes what the
 * list shows has to be able to refresh today's row on the spot. The scheduled
 * run and that refresh must write identical rows, which they can only be relied
 * on to do by being the same code.
 *
 * Re-running a date is safe: rows upsert on (inventory_item_id, snapshot_date),
 * so today's row is overwritten in place and history stays one row per day.
 */
class InventoryItemSnapshotter
{
    /** Insert in batches so a large workspace does not build one enormous query. */
    private const CHUNK = 500;

    public function __construct(private Workspace $workspace, private string $date) {}

    /**
     * Freeze the workspace's items for the date.
     *
     * @param  list<int>|null  $itemIds  only these items, or null for all of them.
     *                                   A single-item refresh still costs one pass
     *                                   over the order feed, so it is meant for an
     *                                   edit rather than for a loop.
     * @return int rows written
     */
    public function refresh(?array $itemIds = null): int
    {
        $query = $this->query();

        if ($itemIds !== null) {
            if (! $itemIds) {
                return 0;
            }

            $query->whereIn('inventory_items.id', $itemIds);
        }

        $facts = new ItemReportFacts($this->workspace, $this->groupsFor($itemIds));
        $demandAsOf = $facts->demandAsOf()?->toDateString();
        $now = now();
        $written = 0;

        $query->orderBy('inventory_items.id')
            ->chunk(self::CHUNK, function ($items) use ($facts, $demandAsOf, $now, &$written) {
                $rows = $items->map(fn (InventoryItem $item) => $this->row($item, $facts, $demandAsOf, $now))->all();

                DB::table('inventory_item_snapshots')->upsert(
                    $rows,
                    ['inventory_item_id', 'snapshot_date'],
                    self::updatableColumns(),
                );

                $written += count($rows);
            });

        return $written;
    }

    /**
     * The columns an upsert refreshes on a row that already exists. Everything
     * except the keys it matched on and created_at, which belongs to the first
     * write of the day.
     *
     * @return list<string>
     */
    public static function updatableColumns(): array
    {
        return [
            'workspace_id', 'product_id', 'parent_id', 'is_parent', 'sku', 'is_active',
            'sales_keywords', 'transaction_keywords', 'lead_time', 'days_of_coverage',
            'unfulfilled_count', 'three_days_average', 'remaining_qty', 'item_created_at',
            'current_stocks', 'discrepancy', 'discrepancy_counted_qty', 'discrepancy_date',
            'waiting_for_delivery_stocks', 'requested_stocks', 'remaining_after_fulfillment',
            'stocks_needed_for_lead_time', 'po_qty', 'po_needed', 'days_it_can_last',
            'product_name', 'product_winning_date', 'updated_at', 'demand_as_of',
            ...ItemReportFacts::SNAPSHOT_COLUMNS,
        ];
    }

    /**
     * Read through the same computed SQL the list uses, so the snapshot is a true
     * photo of the page rather than a second, subtly different calculation.
     */
    private function query()
    {
        $query = InventoryItem::query()
            ->where('inventory_items.workspace_id', $this->workspace->id)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->tap(fn ($q) => InventoryStockColumns::applyJoins($q))
            ->select([
                'inventory_items.id',
                'inventory_items.workspace_id',
                'inventory_items.product_id',
                'inventory_items.parent_id',
                'inventory_items.is_parent',
                'inventory_items.sku',
                'inventory_items.is_active',
                'inventory_items.sales_keywords',
                'inventory_items.transaction_keywords',
                'inventory_items.lead_time',
                'inventory_items.days_of_coverage',
                'inventory_items.unfulfilled_count',
                'inventory_items.three_days_average',
                'inventory_items.remaining_qty',
                'inventory_items.created_at as item_created_at',
                'products.name as product_name',
                'products.winning_date as product_winning_date',
            ]);

        foreach (InventoryItemMetrics::sql() as $alias => $sql) {
            $query->selectRaw("$sql as $alias");
        }

        return $query;
    }

    /**
     * The groups the report figures are needed for.
     *
     * A partial refresh only touches the items named, but their figures are their
     * group's, so the group is what gets scoped — narrowing further would compute
     * a demand total against a fraction of the group and freeze it as the whole.
     *
     * @param  list<int>|null  $itemIds
     * @return list<int>|null
     */
    private function groupsFor(?array $itemIds): ?array
    {
        if ($itemIds === null) {
            return null;
        }

        return InventoryItem::whereIn('id', $itemIds)
            ->get(['id', 'parent_id'])
            ->map(fn ($item) => (int) ($item->parent_id ?? $item->id))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function row(InventoryItem $item, ItemReportFacts $facts, ?string $demandAsOf, $now): array
    {
        return [
            'workspace_id' => $item->workspace_id,
            'inventory_item_id' => $item->id,
            'snapshot_date' => $this->date,

            'product_id' => $item->product_id,
            'parent_id' => $item->parent_id,
            'is_parent' => (bool) $item->is_parent,
            'sku' => $item->sku,
            'is_active' => (bool) $item->is_active,
            'sales_keywords' => $item->getRawOriginal('sales_keywords'),
            'transaction_keywords' => $item->getRawOriginal('transaction_keywords'),
            'lead_time' => $item->lead_time ?? 0,
            'days_of_coverage' => $item->days_of_coverage ?? 0,
            'unfulfilled_count' => $item->unfulfilled_count ?? 0,
            'three_days_average' => $item->three_days_average ?? 0,
            'remaining_qty' => $item->remaining_qty,
            'item_created_at' => $item->item_created_at,

            'current_stocks' => $item->current_stocks,
            'discrepancy' => $item->discrepancy,
            'discrepancy_counted_qty' => $item->discrepancy_counted_qty,
            'discrepancy_date' => $item->discrepancy_date,
            'waiting_for_delivery_stocks' => $item->waiting_for_delivery_stocks,
            'requested_stocks' => $item->requested_stocks,
            'remaining_after_fulfillment' => $item->remaining_after_fulfillment,
            'stocks_needed_for_lead_time' => $item->stocks_needed_for_lead_time,
            'po_qty' => $item->po_qty,
            'po_needed' => $item->po_needed,
            'days_it_can_last' => $item->days_it_can_last,

            'product_name' => $item->product_name,
            'product_winning_date' => $item->product_winning_date,

            // The report's figures, which belong to the group rather than the
            // SKU. Written onto every row of the group so a snapshot row stays
            // self-describing; the roll-up reads them back with MAX(), which is
            // exact because they are identical across it.
            ...array_intersect_key(
                $facts->for((int) ($item->parent_id ?? $item->id)),
                array_flip(ItemReportFacts::SNAPSHOT_COLUMNS),
            ),
            'demand_as_of' => $demandAsOf,

            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
