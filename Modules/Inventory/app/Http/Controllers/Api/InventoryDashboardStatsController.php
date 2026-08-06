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
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
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

    /** How many groups the high-unfulfilled table lists. */
    private const HIGH_UNFULFILLED_LIMIT = 20;

    /** How many groups the low-stock table lists. */
    private const LOW_STOCK_LIMIT = 20;

    /** How far back the delivery lead-time table looks for purchase orders, in months. */
    private const LEAD_TIME_MONTHS = 6;

    /**
     * Fill levels the lead-time table reports, as a percentage of the ordered
     * quantity. Mirrored on the frontend by delivery-lead-time-table.tsx.
     */
    private const LEAD_TIME_THRESHOLDS = [25, 50, 75, 100];

    /** How many groups the delivery lead-time table lists, slowest first. */
    private const LEAD_TIME_LIMIT = 200;

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

    /**
     * The items owing the most stock, worst first — where the Unfulfilled tile's
     * total is actually concentrated.
     *
     * Counted per GROUP, not per SKU: children roll into their parent on
     * COALESCE(parent_id, id) and their counts are summed, so a grouped SKU
     * appears once under the parent's name with the group's total rather than
     * scattered across the table as several smaller rows. Same grouping the
     * items list's summarize view and the Inventory Items tile use.
     *
     * Only groups actually owing something are returned — a zero row is not a
     * problem to look at — and the list is capped, since the point is the worst
     * offenders rather than a full inventory.
     */
    public function highUnfulfilled(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        // Per-item rows first (parents included, so their children roll into
        // them), then aggregated by group below — mirrors
        // InventoryItemController::buildSummaryQuery().
        $inner = $this->activeItems($request, $workspace)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.unfulfilled_count, products.name as product_name');

        $groupUnfulfilled = 'COALESCE(SUM(sub.unfulfilled_count), 0)';

        $rows = DB::query()
            ->fromSub($inner, 'sub')
            // Identity comes from the parent when the group has one, else from
            // the standalone item itself.
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.id END), MAX(sub.id)) as id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.sku END), MAX(sub.sku)) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_name END), MAX(sub.product_name)) as product_name')
            ->selectRaw('MAX(CASE WHEN sub.is_parent = 1 THEN 1 ELSE 0 END) as is_group')
            // Excludes the parent placeholder, so a standalone item is a group of one.
            ->selectRaw('SUM(CASE WHEN sub.is_parent = 0 THEN 1 ELSE 0 END) as child_count')
            ->selectRaw("$groupUnfulfilled as unfulfilled_count")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)')
            ->havingRaw("$groupUnfulfilled > 0")
            ->orderByDesc('unfulfilled_count')
            // Tie-break so equal counts keep a stable order between refreshes.
            ->orderBy('sku')
            ->limit(self::HIGH_UNFULFILLED_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'is_group' => (bool) $row->is_group,
                'child_count' => (int) $row->child_count,
                'unfulfilled_count' => (int) round((float) $row->unfulfilled_count),
            ]);

        return response()->json([
            'items' => $rows,
            // The listed rows only — the workspace-wide total is the KPI tile's
            // job, and reusing it here would misdescribe this table.
            'listed_unfulfilled' => $rows->sum('unfulfilled_count'),
            'limit' => self::HIGH_UNFULFILLED_LIMIT,
        ]);
    }

    /**
     * The items most in need of reordering, worst first — what to raise a
     * purchase order for next.
     *
     * Grouped by parent like its sibling table, but PO Needed cannot simply be
     * summed across a group: each child's figure carries the same lead-time and
     * buffer demand, so adding them would count that demand once per child.
     * It is recomputed from the group's summed parts instead — the group's
     * representative lead time and days-of-coverage against its summed daily
     * average and summed remaining stock — exactly as
     * InventoryItemController::buildSummaryQuery() does for the items list.
     */
    public function lowStock(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Inventory Items', $workspace);

        $inner = $this->activeItems($request, $workspace)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.lead_time, inventory_items.days_of_coverage, inventory_items.three_days_average, products.name as product_name')
            ->selectRaw(InventoryStockColumns::currentStocks().' as current_stocks')
            ->selectRaw(InventoryStockColumns::remainingAfterFulfillment().' as remaining_after_fulfillment');

        // The group's lead time and buffer are the parent's when it has one,
        // else the max across the group — same rule the items list applies.
        $groupLeadTime = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.lead_time END), MAX(sub.lead_time))';
        $groupDaysOfCoverage = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.days_of_coverage END), MAX(sub.days_of_coverage))';
        $summedThreeDayAvg = 'SUM(sub.three_days_average)';
        $summedRemaining = 'COALESCE(SUM(sub.remaining_after_fulfillment), 0)';
        $groupPoNeeded = "GREATEST(0, ($groupDaysOfCoverage * $summedThreeDayAvg) + ($groupLeadTime * $summedThreeDayAvg) - $summedRemaining)";

        $rows = DB::query()
            ->fromSub($inner, 'sub')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.id END), MAX(sub.id)) as id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.sku END), MAX(sub.sku)) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_name END), MAX(sub.product_name)) as product_name')
            ->selectRaw('MAX(CASE WHEN sub.is_parent = 1 THEN 1 ELSE 0 END) as is_group')
            ->selectRaw('SUM(CASE WHEN sub.is_parent = 0 THEN 1 ELSE 0 END) as child_count')
            ->selectRaw('SUM(sub.current_stocks) as current_stocks')
            ->selectRaw("$groupPoNeeded as po_needed")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)')
            // Groups already covered need no purchase order — listing them as
            // "low stock" would be wrong.
            ->havingRaw("$groupPoNeeded > 0")
            ->orderByDesc('po_needed')
            // Tie-break so equal figures keep a stable order between refreshes.
            ->orderBy('sku')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'is_group' => (bool) $row->is_group,
                'child_count' => (int) $row->child_count,
                // Whole units: you cannot order a fraction of one, and the
                // items list rounds the same way.
                'po_needed' => (int) round((float) $row->po_needed),
                'current_stocks' => $row->current_stocks === null ? null : (int) round((float) $row->current_stocks),
            ]);

        return response()->json([
            'items' => $rows,
            'listed_po_needed' => $rows->sum('po_needed'),
            'limit' => self::LOW_STOCK_LIMIT,
        ]);
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
                'purchasedOrder:id,control_no,cust_po_no,status,issue_date,expected_delivery_date',
                'inventoryItem:id,sku,product_id',
                'inventoryItem.product:id,name',
            ])
            ->withSum('deliveries as delivered_qty', 'qty')
            ->get()
            // balance is max(0, count - delivered), so this drops both fully and
            // over-delivered lines.
            ->filter(fn (PurchasedOrderItem $line) => $line->balance > 0)
            // Most recently issued order first. Orders with no issue date sort
            // last rather than leading the table — PHP_INT_MIN, since the sort
            // runs descending.
            ->sortByDesc(fn (PurchasedOrderItem $line) => $line->purchasedOrder->issue_date?->timestamp ?? PHP_INT_MIN)
            ->values()
            ->map(fn (PurchasedOrderItem $line) => [
                'id' => $line->id,
                'purchased_order_id' => $line->purchasedOrder->id,
                'control_no' => $line->purchasedOrder->control_no,
                'cust_po_no' => $line->purchasedOrder->cust_po_no,
                // Date-only strings; the client formats them for display.
                'issue_date' => $line->purchasedOrder->issue_date?->toDateString(),
                'expected_delivery_date' => $line->purchasedOrder->expected_delivery_date?->toDateString(),
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
     * How long purchase orders actually take to arrive, per inventory item.
     *
     * For each PO line the clock starts on the order's issue date and stops at
     * the delivery that carried cumulative receipts past each fill level — 25%,
     * 50%, 75% and 100% of the ordered quantity. A line contributes to every
     * level it has already crossed, so partially delivered lines count toward
     * the levels they have reached and are simply absent from the ones they
     * have not. That means each column has its own sample size (returned as
     * `samples`), and the columns get progressively thinner to the right.
     *
     * Only orders issued within the last LEAD_TIME_MONTHS months are counted,
     * so the figures track current supplier performance rather than history.
     * Cancelled orders are dropped: they were never going to arrive, and
     * counting them as "never reached 100%" would libel the supplier.
     *
     * With `group_by_parent` on (the default) children roll into their parent
     * the way the items list's summarize view shows them; off, every SKU is its
     * own row. Rows are listed alphabetically by product name, SKU as the
     * tie-break — see the two-stage sort below for how that meets the cap.
     */
    public function deliveryLeadTime(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('View Purchased Orders', $workspace);

        $groupByParent = $request->boolean('group_by_parent', true);
        $since = CarbonImmutable::today()->subMonths(self::LEAD_TIME_MONTHS);

        $lines = PurchasedOrderItem::query()
            ->whereHas('purchasedOrder', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', '!=', PurchasedOrder::CANCELLED)
                // Undated orders have no clock to start, so they cannot
                // contribute a lead time — the range filter drops them too.
                ->whereNotNull('issue_date')
                ->where('issue_date', '>=', $since->toDateString())
                ->visibleTo($request->user(), $workspace))
            // A zero/absent ordered quantity has no fill levels to cross.
            ->where('count', '>', 0)
            ->with([
                'purchasedOrder:id,issue_date',
                'deliveries:id,inventory_purchased_order_item_id,delivery_date,qty',
                'inventoryItem:id,sku,parent_id,product_id',
                'inventoryItem.product:id,name',
                'inventoryItem.parent:id,sku,product_id',
                'inventoryItem.parent.product:id,name',
            ])
            ->get();

        $groups = [];
        $overallSums = array_fill_keys(self::LEAD_TIME_THRESHOLDS, 0.0);
        $overallSamples = array_fill_keys(self::LEAD_TIME_THRESHOLDS, 0);

        foreach ($lines as $line) {
            $item = $line->inventoryItem;

            // A PO line pointing at a deleted item has nothing to group under.
            if (! $item) {
                continue;
            }

            $parent = $groupByParent ? $item->parent : null;
            $key = $parent?->id ?? $item->id;

            if (! isset($groups[$key])) {
                $display = $parent ?? $item;

                $groups[$key] = [
                    'id' => (int) $display->id,
                    'sku' => $display->sku,
                    'product_name' => $display->product?->name,
                    'is_group' => $parent !== null,
                    // Distinct SKUs that actually contributed a line, not every
                    // child on file — a row's figures come only from these.
                    'child_ids' => [],
                    'lines' => 0,
                    'sums' => array_fill_keys(self::LEAD_TIME_THRESHOLDS, 0.0),
                    'samples' => array_fill_keys(self::LEAD_TIME_THRESHOLDS, 0),
                ];
            }

            $groups[$key]['lines']++;
            $groups[$key]['child_ids'][$item->id] = true;

            foreach ($this->fillLevelDays($line) as $threshold => $days) {
                if ($days === null) {
                    continue;
                }

                $groups[$key]['sums'][$threshold] += $days;
                $groups[$key]['samples'][$threshold]++;
                $overallSums[$threshold] += $days;
                $overallSamples[$threshold]++;
            }
        }

        $rows = collect($groups)
            ->map(fn (array $group) => [
                'id' => $group['id'],
                'sku' => $group['sku'],
                'product_name' => $group['product_name'],
                'is_group' => $group['is_group'],
                'child_count' => count($group['child_ids']),
                'lines' => $group['lines'],
                'averages' => $this->averageDays($group['sums'], $group['samples']),
                'samples' => $group['samples'],
            ])
            ->values();

        // Two different sorts, deliberately. The cap has to keep the rows worth
        // seeing, so which rows survive is decided slowest-first; the rows that
        // do survive are then listed by name, which is how you actually find an
        // item in a long table. The client says as much when the cap bites.
        $listed = $rows
            ->sortBy([
                // Rows that have not reached a level yet have no average there,
                // so they sink in that comparison rather than reading as fast.
                fn (array $a, array $b) => ($b['averages'][100] ?? -1) <=> ($a['averages'][100] ?? -1),
                fn (array $a, array $b) => ($b['averages'][75] ?? -1) <=> ($a['averages'][75] ?? -1),
                // Tie-break so equal figures keep a stable cut between refreshes.
                fn (array $a, array $b) => ($a['sku'] ?? '') <=> ($b['sku'] ?? ''),
            ])
            ->take(self::LEAD_TIME_LIMIT)
            ->sortBy([
                // Unnamed items sort last rather than leading the table under a
                // blank; their SKU still orders them among themselves.
                fn (array $a, array $b) => ($a['product_name'] === null) <=> ($b['product_name'] === null),
                fn (array $a, array $b) => strcasecmp((string) $a['product_name'], (string) $b['product_name']),
                fn (array $a, array $b) => strcasecmp((string) $a['sku'], (string) $b['sku']),
            ])
            ->values();

        return response()->json([
            'items' => $listed,
            // The client says so when these differ, rather than presenting a
            // capped list as the whole picture.
            'total_groups' => $rows->count(),
            'limit' => self::LEAD_TIME_LIMIT,
            'thresholds' => self::LEAD_TIME_THRESHOLDS,
            'months' => self::LEAD_TIME_MONTHS,
            'since' => $since->toDateString(),
            'group_by_parent' => $groupByParent,
            // Across every line in the window, not an average of the row
            // averages — a row with one line would otherwise weigh as much as
            // one with fifty.
            'overall' => [
                'lines' => $lines->count(),
                'averages' => $this->averageDays($overallSums, $overallSamples),
                'samples' => $overallSamples,
            ],
        ]);
    }

    /**
     * Days from the order's issue date to the delivery that took this line past
     * each fill level, or null for levels it has not reached yet.
     *
     * Deliveries are walked oldest first and accumulated, so a level is stamped
     * with the date it was actually crossed — several levels land on the same
     * date when one delivery jumps past them all. Comparison is cross-multiplied
     * (`delivered * 100 >= ordered * threshold`) to keep it in integers.
     *
     * @return array<int, int|null>
     */
    private function fillLevelDays(PurchasedOrderItem $line): array
    {
        $days = array_fill_keys(self::LEAD_TIME_THRESHOLDS, null);
        $issued = $line->purchasedOrder?->issue_date;
        $ordered = (int) $line->count;

        if (! $issued || $ordered <= 0) {
            return $days;
        }

        $deliveries = $line->deliveries
            ->filter(fn (PurchasedOrderItemDelivery $delivery) => $delivery->delivery_date !== null)
            ->sortBy(fn (PurchasedOrderItemDelivery $delivery) => $delivery->delivery_date->getTimestamp());

        $cumulative = 0;
        $fullyReceived = self::LEAD_TIME_THRESHOLDS[array_key_last(self::LEAD_TIME_THRESHOLDS)];

        foreach ($deliveries as $delivery) {
            // Negative correction rows would otherwise walk the total backwards
            // after a level has already been stamped.
            $cumulative += max(0, (int) $delivery->qty);

            // Both are `date:` casts, so already midnight — the difference is
            // whole days. A delivery dated before its order is data noise
            // (back-dated receipt, mistyped issue date); floor it at same-day
            // rather than let a negative lead time drag the average down.
            $elapsed = max(0, (int) $issued->diffInDays($delivery->delivery_date, absolute: false));

            foreach (self::LEAD_TIME_THRESHOLDS as $threshold) {
                if ($days[$threshold] === null && $cumulative * 100 >= $ordered * $threshold) {
                    $days[$threshold] = $elapsed;
                }
            }

            // Nothing left to stamp once the line is fully received.
            if ($days[$fullyReceived] !== null) {
                break;
            }
        }

        return $days;
    }

    /**
     * Mean days per fill level, to one decimal. Null where no line has reached
     * that level — an unreached level is unknown, not zero.
     *
     * @param  array<int, float>  $sums
     * @param  array<int, int>  $samples
     * @return array<int, float|null>
     */
    private function averageDays(array $sums, array $samples): array
    {
        $averages = [];

        foreach (self::LEAD_TIME_THRESHOLDS as $threshold) {
            $averages[$threshold] = $samples[$threshold] > 0
                ? round($sums[$threshold] / $samples[$threshold], 1)
                : null;
        }

        return $averages;
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
