<?php

namespace Modules\Inventory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Support\InventoryItemMetrics;
use Modules\Inventory\Support\InventoryStockColumns;

/**
 * Freeze every inventory item — stored columns and computed metrics alike — against
 * a date, so the items list can be filtered back to that day. Scheduled daily; see
 * routes/console.php.
 *
 * Re-running for a date is safe: rows are upserted on (inventory_item_id,
 * snapshot_date), so a manual backfill overwrites rather than duplicates.
 */
class SnapshotInventoryItemsCommand extends Command
{
    protected $signature = 'inventory:snapshot-items
                            {--date= : Date to tag the snapshot with (Y-m-d), defaults to today}
                            {--workspace= : Limit to a single workspace id}';

    protected $description = 'Store a daily snapshot of every inventory item and its computed stock metrics';

    /** Insert in batches so a large workspace does not build one enormous query. */
    private const CHUNK = 500;

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $metrics = InventoryItemMetrics::sql();
        $now = now();

        // Read through the same computed SQL the list uses, so the snapshot is a true
        // photo of the page rather than a second, subtly different calculation.
        $query = InventoryItem::query()
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

        foreach ($metrics as $alias => $sql) {
            $query->selectRaw("$sql as $alias");
        }

        if ($workspaceId = $this->option('workspace')) {
            $query->where('inventory_items.workspace_id', $workspaceId);
        }

        $written = 0;

        $query->orderBy('inventory_items.id')
            ->chunk(self::CHUNK, function ($items) use ($date, $now, &$written) {
                $rows = $items->map(fn ($item) => [
                    'workspace_id' => $item->workspace_id,
                    'inventory_item_id' => $item->id,
                    'snapshot_date' => $date,

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

                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                // Upsert on the (item, date) unique key: re-running a date refreshes it.
                DB::table('inventory_item_snapshots')->upsert(
                    $rows,
                    ['inventory_item_id', 'snapshot_date'],
                    [
                        'workspace_id', 'product_id', 'parent_id', 'is_parent', 'sku', 'is_active',
                        'sales_keywords', 'transaction_keywords', 'lead_time', 'days_of_coverage',
                        'unfulfilled_count', 'three_days_average', 'remaining_qty', 'item_created_at',
                        'current_stocks', 'discrepancy', 'discrepancy_counted_qty', 'discrepancy_date',
                        'waiting_for_delivery_stocks', 'requested_stocks', 'remaining_after_fulfillment',
                        'stocks_needed_for_lead_time', 'po_qty', 'po_needed', 'days_it_can_last',
                        'product_name', 'product_winning_date', 'updated_at',
                    ]
                );

                $written += count($rows);
            });

        $this->info("Snapshotted {$written} inventory item(s) for {$date}.");

        return self::SUCCESS;
    }
}
