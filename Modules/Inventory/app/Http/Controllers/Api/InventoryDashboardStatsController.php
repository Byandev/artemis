<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Support\InventoryStockColumns;

/**
 * Per-KPI endpoints for the inventory dashboard. Each statistic is resolved on
 * its own so the frontend can load, skeleton, and refresh it independently —
 * one focused query per request instead of one heavy page load.
 *
 * Every figure is a snapshot of the current ledger; there is no date window.
 * Stock-derived numbers reuse InventoryStockColumns, so the tiles agree with
 * the Inventory Items list to the unit.
 */
class InventoryDashboardStatsController extends Controller
{
    use AuthorizesRequests;

    /** Active tracked SKUs. */
    public function inventoryItems(Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        return response()->json([
            'value' => $this->activeItems($workspace)->count(),
        ]);
    }

    /**
     * Units on hand across active items — ledger stock plus the latest physical
     * count offset, the same figure the items list shows per row.
     */
    public function totalStocks(Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $total = $this->activeItems($workspace)
            ->sum(DB::raw('COALESCE('.InventoryStockColumns::currentStocks().', 0)'));

        return response()->json(['value' => (int) round((float) $total)]);
    }

    /** Units already sold that stock has yet to cover. */
    public function unfulfilled(Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $total = $this->activeItems($workspace)->sum('unfulfilled_count');

        return response()->json(['value' => (int) round((float) $total)]);
    }

    /** Purchase orders still owing stock (not yet delivered or cancelled). */
    public function openPos(Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $count = PurchasedOrder::where('workspace_id', $workspace->id)
            ->whereNotIn('status', PurchasedOrder::CLOSED_STATUSES)
            ->count();

        return response()->json(['value' => $count]);
    }

    /**
     * Base scope the item-derived figures build on. InventoryStockColumns'
     * fragments are written against the `inventory_items` alias, so the columns
     * stay table-qualified here.
     */
    private function activeItems(Workspace $workspace): Builder
    {
        return InventoryItem::where('inventory_items.workspace_id', $workspace->id)
            ->where('inventory_items.is_active', true);
    }
}
