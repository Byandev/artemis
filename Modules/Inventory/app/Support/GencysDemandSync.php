<?php

namespace Modules\Inventory\Support;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

/**
 * Recomputes how much of each item is ordered but not yet shipped.
 *
 * unfulfilled_count is what the reorder maths subtracts before deciding what to
 * buy, so it is written here and then frozen by the snapshot in the same run —
 * see SnapshotInventoryItemsCommand. Running them apart is how the page comes
 * to show one run's demand against the next run's stock.
 *
 * The 3-day sales rate used to be computed here too, into
 * inventory_items.three_days_average. It is not any more: ItemReportFacts
 * already counted the same orders over the same window to produce units_3d, and
 * two independent counts of one measurement is exactly how the list came to
 * show two different numbers for it. units_3d is now the only one, recorded per
 * item and frozen straight into the snapshot. The column it used to write
 * survives for workspaces with no Gencys feed, which fill it by hand or through
 * the public API.
 *
 * A Gencys order line names a unit code rather than an item, so each line is
 * expanded through inventory_unit_code_items into its components. The line's own
 * quantity is deliberately ignored: one line is one bundle, and the bundle's
 * composition says how many units of each item that is.
 */
class GencysDemandSync
{
    /** Order statuses that count as unfulfilled — committed, not yet shipped. */
    private const UNFULFILLED_STATUSES = ['New', 'PENDING PRINTED WAYBILL', 'ENCODED'];

    public function __construct(private Workspace $workspace) {}

    /**
     * Workspaces this can run for: a Gencys partner with ERP credentials on file.
     *
     * Both halves matter. The partner flag is the intent — this workspace's
     * inventory is driven by Gencys — and the credentials are the evidence that
     * the feed is actually wired up. Without them the order tables are empty or
     * stale, and running anyway would overwrite real demand with zeroes.
     *
     * @return Builder<Workspace>
     */
    public static function eligibleWorkspaces()
    {
        return Workspace::query()
            ->where('is_gencys_partner', true)
            ->whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->where('erp_password', '!=', '');
    }

    /**
     * Whether one already-loaded workspace qualifies, without a round trip.
     *
     * Mirrors eligibleWorkspaces() exactly — the two are the same rule, one for
     * filtering a query and one for asking about a model in hand.
     */
    public static function isEligible(Workspace $workspace): bool
    {
        return (bool) $workspace->is_gencys_partner
            && filled($workspace->erp_username)
            && filled($workspace->erp_password);
    }

    /**
     * Rewrite unfulfilled_count for every item.
     *
     * @return int items written
     */
    public function run(): int
    {
        $componentsByKey = $this->componentsByUnitCode();

        if (! $componentsByKey) {
            // No unit codes means no way to attribute an order line to an item.
            // Writing zeroes would read as "nothing is selling" rather than
            // "we cannot tell", and every cover figure would follow it.
            return 0;
        }

        $unfulfilled = $this->expand(
            $this->occurrences(fn ($q) => $q->whereIn('gencys_orders.parcel_status', self::UNFULFILLED_STATUSES)),
            $componentsByKey,
        );

        $items = InventoryItem::where('workspace_id', $this->workspace->id)->get(['id', 'sku']);

        foreach ($items as $item) {
            // Match on the normalised SKU so demand keyed by item_code lines up
            // even when case or whitespace differs.
            $key = $this->normalize((string) $item->sku);

            InventoryItem::where('id', $item->id)->update([
                'unfulfilled_count' => $unfulfilled[$key] ?? 0,
            ]);
        }

        return $items->count();
    }

    /**
     * Build the unit-code → component-items lookup. Keyed by both the unit
     * code's label and its sku, because an order line names it by either.
     *
     * @return array<string, array<string, int>>
     */
    private function componentsByUnitCode(): array
    {
        $components = [];

        foreach (DB::table('inventory_unit_code_items')->where('workspace_id', $this->workspace->id)->get(['unit_code', 'item_code', 'quantity']) as $row) {
            if ($row->item_code === null) {
                continue;
            }

            $code = $this->normalize((string) $row->unit_code);
            $itemCode = $this->normalize((string) $row->item_code);
            $components[$code][$itemCode] = ($components[$code][$itemCode] ?? 0) + (int) $row->quantity;
        }

        $map = [];

        foreach (DB::table('inventory_unit_codes')->where('workspace_id', $this->workspace->id)->get(['unit_code', 'sku']) as $unitCode) {
            $parts = $components[$this->normalize((string) $unitCode->unit_code)] ?? null;

            if ($parts === null) {
                continue;
            }

            foreach (array_filter([$unitCode->unit_code, $unitCode->sku]) as $key) {
                $map[$this->normalize((string) $key)] = $parts;
            }
        }

        return $map;
    }

    /**
     * How many order lines reference each unit-code sku, within the constraint.
     * The line's own quantity is intentionally ignored — see the class docblock.
     *
     * @return Collection<string, int>
     */
    private function occurrences(callable $constrain): Collection
    {
        $query = DB::table('gencys_order_items')
            ->join('gencys_orders', 'gencys_orders.id', '=', 'gencys_order_items.order_id')
            ->where('gencys_orders.workspace_id', $this->workspace->id)
            ->whereNotNull('gencys_order_items.sku');

        $constrain($query);

        return $query
            ->select('gencys_order_items.sku', DB::raw('COUNT(*) as occurrences'))
            ->groupBy('gencys_order_items.sku')
            ->pluck('occurrences', 'gencys_order_items.sku');
    }

    /**
     * Expand unit-code occurrences into per-item demand:
     * demand[item_code] += occurrences × component-qty-per-bundle.
     *
     * @param  Collection<string, int>  $occurrences
     * @param  array<string, array<string, int>>  $componentsByKey
     * @return array<string, int>
     */
    private function expand(Collection $occurrences, array $componentsByKey): array
    {
        $demand = [];

        foreach ($occurrences as $sku => $count) {
            $components = $componentsByKey[$this->normalize((string) $sku)] ?? null;

            if ($components === null) {
                continue;
            }

            foreach ($components as $itemCode => $qtyPerBundle) {
                $demand[$itemCode] = ($demand[$itemCode] ?? 0) + ($count * $qtyPerBundle);
            }
        }

        return $demand;
    }

    /** Normalise a key for forgiving matching (trim + uppercase). */
    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
