<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryUnitCode;

/**
 * Daily inventory demand sync driven by Gencys orders (the SyncInventoryAverage
 * equivalent, sourced from gencys_orders instead of Pancake).
 *
 * Each Gencys order line is a unit code (its sku is the unit-code label parsed
 * from the order string). We do NOT use the line's own quantity — we expand the
 * unit code into its inventory_unit_code_items and use each component's quantity.
 * Per inventory item we then write:
 *   - three_days_average : component qty summed over the last 3 full days
 *                          (by order_date) ÷ 3
 *   - unfulfilled_count  : component qty summed over orders whose order_status
 *                          is still open (see UNFULFILLED_STATUSES)
 */
class SyncInventoryFromGencysOrders extends Command
{
    /** Order statuses that count as unfulfilled (committed but not yet shipped). */
    private const UNFULFILLED_STATUSES = ['New', 'PENDING PRINTED WAYBILL', 'ENCODED'];

    protected $signature = 'gencys-erp:sync-inventory-from-orders';

    protected $description = 'Compute three_days_average and unfulfilled_count on inventory items from Gencys orders, expanding unit codes into their component items';

    public function handle(): int
    {
        $workspaceIds = Workspace::where('is_gencys_partner', true)->pluck('id');

        if ($workspaceIds->isEmpty()) {
            $this->info('No Gencys partner workspaces found.');

            return self::SUCCESS;
        }

        // Last 3 full days, excluding today (matches SyncInventoryAverage).
        $start = now()->subDays(3)->startOfDay();
        $end = now()->startOfDay();

        $totalItems = 0;

        foreach ($workspaceIds as $workspaceId) {
            $totalItems += $this->syncWorkspace($workspaceId, $start, $end);
        }

        $this->info("Synced {$totalItems} inventory item(s) across {$workspaceIds->count()} Gencys partner workspace(s).");

        return self::SUCCESS;
    }

    /**
     * Recompute demand metrics for every inventory item in one workspace.
     */
    private function syncWorkspace(int $workspaceId, $start, $end): int
    {
        // unit-code key (label or sku) => [ item_code => qty-per-bundle ]
        $componentsByKey = $this->componentsByUnitCode($workspaceId);

        // 3-day average demand: order lines within the window.
        $average = $this->expand(
            $this->occurrencesBySku($workspaceId, fn ($q) => $q->whereBetween('gencys_orders.order_date', [$start, $end])),
            $componentsByKey,
        );

        // Unfulfilled: order lines on orders whose status is still open.
        $unfulfilled = $this->expand(
            $this->occurrencesBySku($workspaceId, fn ($q) => $q->whereIn('gencys_orders.parcel_status', self::UNFULFILLED_STATUSES)),
            $componentsByKey,
        );

        $items = InventoryItem::where('workspace_id', $workspaceId)->get(['id', 'sku']);

        foreach ($items as $item) {
            // Match on the normalized SKU so demand keyed by item_code lines up
            // even when case/whitespace differs.
            $key = $this->normalize((string) $item->sku);

            InventoryItem::where('id', $item->id)->update([
                'three_days_average' => round(($average[$key] ?? 0) / 3, 4),
                'unfulfilled_count' => $unfulfilled[$key] ?? 0,
            ]);
        }

        return $items->count();
    }

    /**
     * Build the unit-code → component-items lookup for a workspace. Keyed by both
     * the unit_code label and its sku so an order line matches on either.
     *
     * @return array<string, array<string, int>>
     */
    private function componentsByUnitCode(int $workspaceId): array
    {
        $unitCodes = InventoryUnitCode::where('workspace_id', $workspaceId)
            ->with(['items' => fn ($q) => $q->where('workspace_id', $workspaceId)])
            ->get();

        $map = [];

        foreach ($unitCodes as $unitCode) {
            $components = [];
            foreach ($unitCode->items as $item) {
                if ($item->item_code === null) {
                    continue;
                }
                // Key by the normalized item code so it lines up with inventory
                // SKUs that differ only by case/whitespace.
                $code = $this->normalize($item->item_code);
                $components[$code] = ($components[$code] ?? 0) + (int) ($item->quantity ?? 0);
            }

            foreach (array_filter([$unitCode->unit_code, $unitCode->sku]) as $key) {
                $map[$this->normalize($key)] = $components;
            }
        }

        return $map;
    }

    /**
     * Count how many order lines reference each unit-code sku, within the given
     * constraint. The order line's own quantity is intentionally ignored.
     *
     * @return Collection<string, int> unit-code sku => occurrences
     */
    private function occurrencesBySku(int $workspaceId, callable $constrain): Collection
    {
        $query = DB::table('gencys_order_items')
            ->join('gencys_orders', 'gencys_orders.id', '=', 'gencys_order_items.order_id')
            ->where('gencys_orders.workspace_id', $workspaceId)
            ->whereNotNull('gencys_order_items.sku');

        $constrain($query);

        return $query
            ->select('gencys_order_items.sku', DB::raw('COUNT(*) as occurrences'))
            ->groupBy('gencys_order_items.sku')
            ->pluck('occurrences', 'gencys_order_items.sku');
    }

    /**
     * Expand unit-code occurrences into per-inventory-item demand:
     *   demand[item_code] += occurrences × component-qty-per-bundle.
     *
     * @param  Collection<string, int>  $occurrencesBySku
     * @param  array<string, array<string, int>>  $componentsByKey
     * @return array<string, int>
     */
    private function expand(Collection $occurrencesBySku, array $componentsByKey): array
    {
        $demand = [];

        foreach ($occurrencesBySku as $sku => $occurrences) {
            $components = $componentsByKey[$this->normalize((string) $sku)] ?? null;

            if ($components === null) {
                continue;
            }

            foreach ($components as $itemCode => $qtyPerBundle) {
                $demand[$itemCode] = ($demand[$itemCode] ?? 0) + ($occurrences * $qtyPerBundle);
            }
        }

        return $demand;
    }

    /** Normalize a unit-code key for forgiving matching (trim + uppercase). */
    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
