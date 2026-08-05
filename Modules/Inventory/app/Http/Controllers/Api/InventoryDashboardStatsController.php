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
use Modules\Inventory\Models\PurchasedOrderItem;
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
     * Trailing windows the movement chart offers, in days. The first is the
     * default; anything else the client asks for falls back to it.
     */
    private const MOVEMENT_WINDOWS = [7, 14, 30];

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
     * Daily units in vs out over a trailing window, from the transaction ledger.
     * "In" is stock arriving (PO receipts + RTS goods returned to stock); "out"
     * is stock leaving. Write-offs (rts_bad, lost) are deliberately excluded —
     * they are shrinkage, not movement.
     *
     * The window ends yesterday: today is still being written to, so a partial
     * day would render as a slump next to complete ones.
     *
     * Days with no transactions are returned as zeroes so the chart keeps an
     * even axis instead of collapsing gaps.
     */
    public function movement(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $days = $request->integer('days', self::MOVEMENT_WINDOWS[0]);

        if (! in_array($days, self::MOVEMENT_WINDOWS, true)) {
            $days = self::MOVEMENT_WINDOWS[0];
        }

        $end = CarbonImmutable::yesterday();
        $start = $end->subDays($days - 1);

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
     * Every line still owing stock on an open purchase order — what the
     * movement chart's "in" arm is waiting on.
     *
     * One row per PO line rather than per item: the same SKU can sit on several
     * open orders, and rolling them together would hide which order is late.
     * Lines already delivered in full are dropped — an order stays open until
     * all of its lines land, so a zero-waiting row is noise.
     */
    public function openPurchaseOrders(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Purchased Orders', $workspace);

        $lines = PurchasedOrderItem::query()
            ->whereHas('purchasedOrder', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', PurchasedOrder::AWAITING_DELIVERY_STATUSES)
                ->visibleTo($request->user(), $workspace))
            ->with([
                'purchasedOrder:id,control_no,cust_po_no,status',
                'inventoryItem:id,sku,product_id',
                'inventoryItem.product:id,name',
            ])
            ->withSum('deliveries as delivered_qty', 'qty')
            ->get()
            // balance is max(0, count - delivered), so this drops both fully and
            // over-delivered lines.
            ->filter(fn (PurchasedOrderItem $line) => $line->balance > 0)
            ->sortByDesc(fn (PurchasedOrderItem $line) => $line->balance)
            ->values()
            ->map(fn (PurchasedOrderItem $line) => [
                'id' => $line->id,
                'purchased_order_id' => $line->purchasedOrder->id,
                'control_no' => $line->purchasedOrder->control_no,
                'cust_po_no' => $line->purchasedOrder->cust_po_no,
                // Numeric status too: the frontend renders it through the
                // shared PURCHASED_ORDER_STATUSES map rather than the label.
                'status' => (int) $line->purchasedOrder->status,
                'status_label' => $line->purchasedOrder->status_label,
                'sku' => $line->inventoryItem?->sku,
                'product_name' => $line->inventoryItem?->product?->name,
                'ordered_qty' => (int) $line->count,
                'delivered_qty' => $line->delivered_qty,
                'waiting_qty' => $line->balance,
            ]);

        return response()->json([
            'lines' => $lines,
            'total_waiting' => $lines->sum('waiting_qty'),
        ]);
    }

    /**
     * Every line on a single purchase order — delivered ones included, unlike
     * the summary listing, which only carries what is still owed. This backs
     * the drill-down, where the point is to see the whole order at once.
     */
    public function purchaseOrderLines(Request $request, Workspace $workspace, PurchasedOrder $purchasedOrder): JsonResponse
    {
        $this->authorize('View Purchased Orders', $workspace);

        abort_unless($purchasedOrder->workspace_id === $workspace->id, 404);

        // Team-scoped users have to reach the order through their team, the
        // same gate the summary listing applies.
        abort_unless(
            PurchasedOrder::whereKey($purchasedOrder->getKey())
                ->visibleTo($request->user(), $workspace)
                ->exists(),
            404
        );

        $lines = PurchasedOrderItem::query()
            ->where('inventory_purchased_order_id', $purchasedOrder->id)
            ->with(['inventoryItem:id,sku,product_id', 'inventoryItem.product:id,name'])
            ->withSum('deliveries as delivered_qty', 'qty')
            ->get()
            // Outstanding lines first; fully delivered ones settle at the end.
            ->sortByDesc(fn (PurchasedOrderItem $line) => $line->balance)
            ->values()
            ->map(fn (PurchasedOrderItem $line) => [
                'id' => $line->id,
                'sku' => $line->inventoryItem?->sku,
                'product_name' => $line->inventoryItem?->product?->name,
                'ordered_qty' => (int) $line->count,
                'delivered_qty' => $line->delivered_qty,
                'waiting_qty' => $line->balance,
                'fulfillment_status' => $line->fulfillment_status,
            ]);

        return response()->json([
            'order' => [
                'id' => $purchasedOrder->id,
                'control_no' => $purchasedOrder->control_no,
                'cust_po_no' => $purchasedOrder->cust_po_no,
                'status_label' => $purchasedOrder->status_label,
            ],
            'lines' => $lines,
            'total_ordered' => $lines->sum('ordered_qty'),
            'total_delivered' => $lines->sum('delivered_qty'),
            'total_waiting' => $lines->sum('waiting_qty'),
        ]);
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
