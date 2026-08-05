<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
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
 *
 * Every figure is also team-scoped: scoped users see only their team(s), and
 * the "viewing as team" switcher narrows the whole dashboard for everyone. The
 * switcher persists its choice to the session, and these endpoints run in the
 * web group (browser-api.php is required from web.php), so the active team
 * resolves without the client passing anything.
 */
class InventoryDashboardStatsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Active items, counted the way the Inventory Items list shows them: one
     * per group. Children roll into their parent (the list's `summarize` view,
     * which is the default), so a grouped SKU counts once rather than once per
     * child. Mirrors buildSummaryQuery()'s `COALESCE(parent_id, id)` grouping.
     */
    public function inventoryItems(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $count = $this->activeItems($request, $workspace)
            ->distinct()
            ->count(DB::raw('COALESCE(inventory_items.parent_id, inventory_items.id)'));

        return response()->json(['value' => $count]);
    }

    /**
     * Units on hand across active items — ledger stock plus the latest physical
     * count offset, the same figure the items list shows per row.
     */
    public function totalStocks(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $total = $this->activeItems($request, $workspace)
            ->sum(DB::raw('COALESCE('.InventoryStockColumns::currentStocks().', 0)'));

        return response()->json(['value' => (int) round((float) $total)]);
    }

    /** Units already sold that stock has yet to cover. */
    public function unfulfilled(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $total = $this->activeItems($request, $workspace)->sum('unfulfilled_count');

        return response()->json(['value' => (int) round((float) $total)]);
    }

    /** Purchase orders still owing stock (not yet delivered or cancelled). */
    public function openPos(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        // visibleTo() reaches teams through items.inventoryItem.product.shops,
        // and is a no-op for unrestricted users with no active team.
        $count = PurchasedOrder::where('workspace_id', $workspace->id)
            ->whereNotIn('status', PurchasedOrder::CLOSED_STATUSES)
            ->visibleTo($request->user(), $workspace)
            ->count();

        return response()->json(['value' => $count]);
    }

    /**
     * Daily units in vs out over the last 7 days, from the transaction ledger.
     * "In" is stock arriving (PO receipts + RTS goods returned to stock); "out"
     * is stock leaving. Write-offs (rts_bad, lost) are deliberately excluded —
     * they are shrinkage, not movement.
     *
     * Days with no transactions are returned as zeroes so the chart keeps an
     * even 7-column axis instead of collapsing gaps.
     */
    public function movement(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $end = CarbonImmutable::today();
        $start = $end->subDays(6);

        $rows = InventoryTransaction::where('workspace_id', $workspace->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->visibleTo($request->user(), $workspace)
            ->groupByRaw('DATE(date)')
            ->selectRaw('DATE(date) as day')
            ->selectRaw('COALESCE(SUM(po_qty_in), 0) + COALESCE(SUM(rts_goods_in), 0) as units_in')
            ->selectRaw('COALESCE(SUM(po_qty_out), 0) + COALESCE(SUM(rts_goods_out), 0) as units_out')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $days = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $days[] = [
                'date' => $key,
                'in' => (int) round((float) ($row->units_in ?? 0)),
                'out' => (int) round((float) ($row->units_out ?? 0)),
            ];
        }

        return response()->json(['days' => $days]);
    }

    /**
     * Base scope the item-derived figures build on. InventoryStockColumns'
     * fragments are written against the `inventory_items` alias, so the columns
     * stay table-qualified here.
     */
    private function activeItems(Request $request, Workspace $workspace): Builder
    {
        $query = InventoryItem::where('inventory_items.workspace_id', $workspace->id)
            ->where('inventory_items.is_active', true);

        $this->applyTeamVisibility($request, $query, $workspace);

        return $query;
    }

    /**
     * Team scoping for inventory items. Deliberately NOT the model's visibleTo()
     * scope: a parent row has no product of its own, so scoping it directly
     * would drop every group from a scoped user's totals. Mirrors
     * InventoryItemController::applySummaryVisibility().
     *
     * @param  Builder<InventoryItem>  $query
     */
    private function applyTeamVisibility(Request $request, Builder $query, Workspace $workspace): void
    {
        $teamIds = TeamVisibility::scopeTeamIds($request->user(), $workspace);

        // null -> unrestricted (or no "viewing as team"): see everything.
        if ($teamIds === null) {
            return;
        }

        // Scoped user with no team -> nothing.
        if (empty($teamIds)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $inTeams = fn ($q) => $q->whereHas(
            'product.shops.teams',
            fn ($t) => $t->whereIn('teams.id', $teamIds),
        );

        $query->where(fn ($q) => $inTeams($q)->orWhereHas('children', $inTeams));
    }
}
