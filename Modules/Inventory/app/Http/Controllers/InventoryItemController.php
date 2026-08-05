<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemExport;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Modules\Inventory\Models\InventoryUnitCodeItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Support\InventoryItemMetrics;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    use AuthorizesRequests;

    /**
     * The correlated-subquery SQL fragments for an item's computed stock columns,
     * all keyed off `inventory_items.id`. Extracted so both the flat list query
     * (buildQuery) and the parent/child roll-up (buildSummaryQuery) compute stock
     * identically. See each fragment's inline note for what it means.
     *
     * @return array<string, string>
     */
    private function stockSql(): array
    {
        // Raw ledger stock = the latest transaction's running remaining_qty.
        $rawCurrentStocksSql = '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';

        // The latest physical-count adjustment (signed), layered on top of the ledger so
        // the displayed stock reflects the last real count. See adjustCount(). Only NULL
        // when the item has neither a transaction nor a count — then the cell stays "—"
        // rather than collapsing to 0.
        $latestDiscrepancySql = '(SELECT discrepancy FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
        $currentStocksSql = "(CASE WHEN $rawCurrentStocksSql IS NULL AND $latestDiscrepancySql IS NULL THEN NULL ELSE COALESCE($rawCurrentStocksSql, 0) + COALESCE($latestDiscrepancySql, 0) END)";

        // The counted quantity and date of that same latest adjustment, surfaced so the
        // list can show what was last counted and when alongside the offset in effect.
        // Identical ORDER BY as above, so all three read from the one latest row.
        $latestCountedSql = '(SELECT counted_qty FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
        $latestDiscrepancyDateSql = '(SELECT date FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';

        // "Waiting for delivery" = the quantity still OWED on orders awaiting delivery —
        // the undelivered remainder per item, not the full ordered count. Fully-delivered
        // lines contribute 0; NULLIF keeps items with nothing outstanding showing as "—".
        $awaitingStatuses = implode(',', PurchasedOrder::AWAITING_DELIVERY_STATUSES);
        $waitingStocksSql = "(SELECT NULLIF(SUM(GREATEST(0, poi.count - COALESCE((SELECT SUM(d.qty) FROM inventory_purchased_order_item_deliveries d WHERE d.inventory_purchased_order_item_id = poi.id), 0))), 0) FROM inventory_purchased_order_items poi WHERE poi.inventory_item_id = inventory_items.id AND EXISTS (SELECT 1 FROM inventory_purchased_orders po WHERE poi.inventory_purchased_order_id = po.id AND po.status in ($awaitingStatuses)))";
        $remainingAfterFulfillmentSql = "(COALESCE($currentStocksSql, 0) + COALESCE($waitingStocksSql, 0) - COALESCE(inventory_items.unfulfilled_count, 0))";
        // Stock needed to cover the lead time = expected demand over that window
        // (daily-ish average × lead-time days). Same term that drives po_needed.
        $stocksNeededForLeadTimeSql = '(COALESCE(inventory_items.lead_time, 0) * COALESCE(inventory_items.three_days_average, 0))';
        // "PO QTY" = the safety buffer on top of lead-time demand: expected demand
        // over days_of_coverage extra days. Surfaced as its own column and folded
        // into PO Needed so reordering covers a runway beyond just the lead time.
        $coverageBufferSql = '(COALESCE(inventory_items.days_of_coverage, 0) * COALESCE(inventory_items.three_days_average, 0))';
        // PO Needed = coverage buffer + lead-time demand − what's on hand after
        // fulfilment, floored at 0.
        $poNeededSql = "GREATEST(0, $coverageBufferSql + $stocksNeededForLeadTimeSql - $remainingAfterFulfillmentSql)";
        $daysItCanLastSql = "(CASE WHEN inventory_items.three_days_average > 0 THEN $remainingAfterFulfillmentSql / inventory_items.three_days_average ELSE 0 END)";

        return [
            'current_stocks' => $currentStocksSql,
            'discrepancy' => $latestDiscrepancySql,
            'discrepancy_counted_qty' => $latestCountedSql,
            'discrepancy_date' => $latestDiscrepancyDateSql,
            'waiting_for_delivery_stocks' => $waitingStocksSql,
            'remaining_after_fulfillment' => $remainingAfterFulfillmentSql,
            'stocks_needed_for_lead_time' => $stocksNeededForLeadTimeSql,
            'po_qty' => $coverageBufferSql,
            'po_needed' => $poNeededSql,
            'days_it_can_last' => $daysItCanLastSql,
        ];
    }

    /**
     * Apply the list's `filter[is_active]` semantics to a base item query: default
     * to active-only, `all` shows everything, an explicit 0/1 narrows.
     */
    private function applyActiveFilter(Request $request, $query): void
    {
        $isActiveFilter = $request->input('filter.is_active');

        if ($isActiveFilter === null) {
            $query->where('inventory_items.is_active', true);
        } elseif ($isActiveFilter !== 'all') {
            $query->where('inventory_items.is_active', filter_var($isActiveFilter, FILTER_VALIDATE_BOOLEAN));
        }
    }

    /**
     * Team-visibility for the summary roll-up. Mirrors the model's visibleTo scope,
     * but keeps a parent placeholder — which has no product of its own and would
     * otherwise fail closed — whenever any of its children is visible, so the parent
     * survives in the group and can supply the row's id/sku/lead time. Unrestricted
     * users are untouched; a scoped user with no team sees nothing (fail-closed).
     */
    private function applySummaryVisibility(Request $request, $query, Workspace $workspace): void
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

        $inTeams = fn ($q) => $q->whereHas('product.shops.teams', fn ($t) => $t->whereIn('teams.id', $teamIds));

        $query->where(function ($q) use ($inTeams) {
            // The item itself is visible via its product's team, OR it's a parent
            // whose children are (a parent has no product to scope on directly).
            $inTeams($q)->orWhereHas('children', $inTeams);
        });
    }

    /**
     * Build the inventory-items query with the same computed columns, filters
     * and sorts the list view uses, so the export mirrors exactly what the
     * table shows. Returns the QueryBuilder un-paginated.
     */
    private function buildQuery(Request $request, Workspace $workspace): QueryBuilder
    {
        $sql = $this->stockSql();

        $base = InventoryItem::where('inventory_items.workspace_id', $workspace->id)
            // Parent placeholders carry no stock of their own — their children do.
            // The flat list rolls nothing up, so a parent would show as an empty
            // row; only the summarize view surfaces the group. Hide them here.
            ->where('inventory_items.is_parent', false)
            // Team scoping: only items whose product's shop is in the user's team
            // (no-op for unrestricted users). Unlinked items are hidden from scoped users.
            ->visibleTo($request->user(), $workspace);
        $this->applyActiveFilter($request, $base);

        // Parent's SKU for child rows, so the flat list can show what each SKU is
        // grouped under. NULL for parents and standalone items.
        $parentSkuSql = '(SELECT sku FROM inventory_items p WHERE p.id = inventory_items.parent_id)';

        return QueryBuilder::for($base)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            ->selectRaw("$parentSkuSql as parent_sku")
            // Undelivered remainder on status-6 orders (see waiting_for_delivery_stocks).
            ->selectRaw("{$sql['waiting_for_delivery_stocks']} as waiting_for_delivery_stocks")
            ->selectRaw("{$sql['current_stocks']} as current_stocks")
            ->selectRaw("{$sql['discrepancy']} as discrepancy")
            ->selectRaw("{$sql['discrepancy_counted_qty']} as discrepancy_counted_qty")
            ->selectRaw("{$sql['discrepancy_date']} as discrepancy_date")
            ->selectRaw("{$sql['remaining_after_fulfillment']} as remaining_after_fulfillment")
            ->selectRaw("{$sql['stocks_needed_for_lead_time']} as stocks_needed_for_lead_time")
            ->selectRaw("{$sql['po_qty']} as po_qty")
            ->selectRaw("{$sql['po_needed']} as po_needed")
            ->selectRaw("{$sql['days_it_can_last']} as days_it_can_last")
            // three_days_average is a stored column updated hourly by inventory:update-averages
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('sku', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('product_id'),
                // Lifecycle stage of the item's linked product. The products
                // table is already left-joined above, so this reads straight off
                // it; unassigned items have a NULL status and drop out, which is
                // the intent — you are filtering by a product attribute.
                AllowedFilter::callback('product_status', function ($query, $value) {
                    $query->whereIn('products.status', (array) $value);
                }),
                // "Unassigned only": items with no linked product. Off unless the
                // toggle is on; a falsy value is a no-op so the key stays valid.
                AllowedFilter::callback('unassigned', function ($query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereNull('inventory_items.product_id');
                    }
                }),
                // is_active is applied manually to $base above; register it as a
                // no-op here so QueryBuilder doesn't reject the filter key.
                AllowedFilter::callback('is_active', function () {}),
                // `date` selects a snapshot in index(). It still has to be a known
                // key here, because a date with no snapshot falls back to this live
                // query with the parameter still on the URL.
                AllowedFilter::callback('date', function () {}),
            ])
            ->allowedSorts([
                'id',
                'product_id',
                'sku',
                'is_active',
                AllowedSort::field('product_name', 'products.name'),
                'lead_time',
                'unfulfilled_count',
                'remaining_qty',
                'three_days_average',
                'current_stocks',
                'waiting_for_delivery_stocks',
                AllowedSort::callback('remaining_after_fulfillment', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['remaining_after_fulfillment']} ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('days_it_can_last', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['days_it_can_last']} ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('po_needed', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['po_needed']} ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('stocks_needed_for_lead_time', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['stocks_needed_for_lead_time']} ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('po_qty', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['po_qty']} ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('discrepancy', function ($query, $descending) use ($sql) {
                    $query->orderByRaw("{$sql['discrepancy']} ".($descending ? 'DESC' : 'ASC'));
                }),
            ])
            ->defaultSort('created_at');
    }

    /**
     * Roll every item up into its group and sum the child values. A "group" is a
     * parent item (is_parent = true) together with the children pointing at it via
     * parent_id; a standalone item is a group of one. Grouping key is
     * COALESCE(parent_id, id) — a child shares its parent's id, a parent/standalone
     * keys off its own id. The displayed row's identity (id, sku, product) comes
     * from the parent when one exists, else from the single item, and the stock
     * columns are SUMmed across the group. Returns a query builder to paginate.
     */
    private function buildSummaryQuery(Request $request, Workspace $workspace): Builder
    {
        $sql = $this->stockSql();

        // Per-item computed rows for the whole workspace (parents included, so their
        // children roll into them). The is_active filter still applies.
        $inner = InventoryItem::query()
            ->where('inventory_items.workspace_id', $workspace->id)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.product_id, inventory_items.is_active, inventory_items.lead_time, inventory_items.days_of_coverage, inventory_items.unfulfilled_count, inventory_items.three_days_average, inventory_items.created_at, products.name as product_name, products.winning_date as product_winning_date')
            ->selectRaw("{$sql['current_stocks']} as current_stocks")
            ->selectRaw("{$sql['waiting_for_delivery_stocks']} as waiting_for_delivery_stocks")
            ->selectRaw("{$sql['discrepancy']} as discrepancy")
            ->selectRaw("{$sql['remaining_after_fulfillment']} as remaining_after_fulfillment")
            ->selectRaw("{$sql['po_needed']} as po_needed");

        $this->applyActiveFilter($request, $inner);
        $this->applySummaryVisibility($request, $inner, $workspace);

        if ($search = $request->input('filter.search')) {
            $inner->where('inventory_items.sku', 'like', "%{$search}%");
        }

        if ($productId = $request->input('filter.product_id')) {
            $inner->where('inventory_items.product_id', $productId);
        }

        // Mirrors the flat list's product_status filter, but keeps the parent
        // placeholder whenever any of its children match — a parent has no
        // product of its own, so filtering it out on products.status would strip
        // the group of the row that supplies its SKU and lead time. Same shape as
        // applySummaryVisibility().
        if ($productStatus = $request->input('filter.product_status')) {
            $statuses = (array) $productStatus;
            $onStatus = fn ($q) => $q->whereHas('product', fn ($p) => $p->whereIn('status', $statuses));

            $inner->where(fn ($q) => $onStatus($q)->orWhereHas('children', $onStatus));
        }

        // "Unassigned only": keep only items with no linked product.
        if ($request->boolean('filter.unassigned')) {
            $inner->whereNull('inventory_items.product_id');
        }

        // po_needed and days_it_can_last are non-additive — summing each child's
        // per-item value would double-count the shared lead-time demand and average
        // wrongly. Recompute them from the group's summed components instead, mirroring
        // the per-item formulas in stockSql() but over the rolled-up totals. The group's
        // lead_time is the representative one (parent's, else the max).
        $groupLeadTime = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.lead_time END), MAX(sub.lead_time))';
        // The group's days-of-coverage buffer, chosen the same way as lead_time:
        // the parent's when there is one, else the max across the group.
        $groupDaysOfCoverage = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.days_of_coverage END), MAX(sub.days_of_coverage))';
        // The group's creation date — the parent's when there is one, else the earliest
        // item in the group. Drives the default ordering below, so it has to be an
        // aggregate: the outer query is grouped and has no bare created_at column.
        $groupCreatedAt = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.created_at END), MIN(sub.created_at))';
        $summedThreeDayAvg = 'SUM(sub.three_days_average)';
        $summedWaiting = 'COALESCE(SUM(sub.waiting_for_delivery_stocks), 0)';
        $summedRemaining = 'COALESCE(SUM(sub.remaining_after_fulfillment), 0)';

        // Stock needed to cover the lead time for the whole group: the group's
        // representative lead time × its summed daily average (same term po_needed uses).
        $groupStocksNeeded = "($groupLeadTime * $summedThreeDayAvg)";
        // Group safety buffer: the group's days-of-coverage × its summed daily average.
        $groupCoverageBuffer = "($groupDaysOfCoverage * $summedThreeDayAvg)";
        $groupPoNeeded = "GREATEST(0, $groupCoverageBuffer + $groupStocksNeeded - $summedRemaining)";
        $groupDaysItCanLast = "(CASE WHEN $summedThreeDayAvg > 0 THEN $summedRemaining / $summedThreeDayAvg ELSE 0 END)";

        // Aggregate the per-item rows into one row per group. Representative
        // identity/attributes prefer the parent row (is_parent = 1), falling back
        // to the item's own for standalone groups. Additive stock columns are summed;
        // po_needed and days_it_can_last are recomputed from the summed totals above.
        $outer = DB::query()
            ->fromSub($inner, 'sub')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.id END), MAX(sub.id)) as id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.sku END), MAX(sub.sku)) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_id END), MAX(sub.product_id)) as product_id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_name END), MAX(sub.product_name)) as product_name')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_winning_date END), MAX(sub.product_winning_date)) as product_winning_date')
            ->selectRaw('MAX(CASE WHEN sub.is_parent = 1 THEN 1 ELSE 0 END) as is_group')
            ->selectRaw('SUM(CASE WHEN sub.is_parent = 0 THEN 1 ELSE 0 END) as child_count')
            ->selectRaw('MAX(sub.is_active) as is_active')
            ->selectRaw("$groupLeadTime as lead_time")
            ->selectRaw('SUM(sub.unfulfilled_count) as unfulfilled_count')
            ->selectRaw('SUM(sub.current_stocks) as current_stocks')
            ->selectRaw('SUM(sub.waiting_for_delivery_stocks) as waiting_for_delivery_stocks')
            ->selectRaw('SUM(sub.discrepancy) as discrepancy')
            ->selectRaw('NULL as discrepancy_counted_qty')
            ->selectRaw('NULL as discrepancy_date')
            ->selectRaw('SUM(sub.remaining_after_fulfillment) as remaining_after_fulfillment')
            ->selectRaw("$groupStocksNeeded as stocks_needed_for_lead_time")
            ->selectRaw("$groupCoverageBuffer as po_qty")
            ->selectRaw("$groupPoNeeded as po_needed")
            ->selectRaw("$summedThreeDayAvg as three_days_average")
            ->selectRaw("$groupDaysItCanLast as days_it_can_last")
            ->selectRaw("$groupCreatedAt as created_at")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)');

        // Sorting on the aggregated aliases; anything unknown falls back to SKU.
        $sortable = [
            'sku', 'is_active', 'lead_time', 'unfulfilled_count', 'current_stocks',
            'waiting_for_delivery_stocks', 'discrepancy', 'remaining_after_fulfillment',
            'stocks_needed_for_lead_time', 'po_qty', 'po_needed', 'three_days_average', 'days_it_can_last',
        ];
        $sort = (string) $request->input('sort');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $outer->orderByRaw("$column ".($descending ? 'DESC' : 'ASC'));
        } else {
            $outer->orderByRaw("$groupCreatedAt ".($descending ? 'DESC' : 'ASC'));
        }

        return $outer;
    }

    /**
     * The flat list as it stood on a past date, read from inventory_item_snapshots.
     * The snapshot table stores the computed metrics under the same aliases the live
     * query derives, so this is the same shape with plain columns instead of
     * correlated subqueries — and the frontend cannot tell the two apart.
     */
    private function buildSnapshotQuery(Request $request, Workspace $workspace, string $date): QueryBuilder
    {
        $base = InventoryItemSnapshot::where('inventory_item_snapshots.workspace_id', $workspace->id)
            ->where('inventory_item_snapshots.snapshot_date', $date)
            // Same as the live list: parents carry no stock of their own and would
            // show as an empty row here.
            ->where('inventory_item_snapshots.is_parent', false)
            ->visibleTo($request->user(), $workspace);

        $this->applySnapshotActiveFilter($request, $base);

        // The parent's SKU as recorded that day, so a child row still shows what it
        // was grouped under even if the grouping has changed since.
        $parentSkuSql = '(SELECT p.sku FROM inventory_item_snapshots p WHERE p.inventory_item_id = inventory_item_snapshots.parent_id AND p.snapshot_date = inventory_item_snapshots.snapshot_date LIMIT 1)';

        return QueryBuilder::for($base)
            ->select('inventory_item_snapshots.*')
            // The list keys rows off `id`; hand it the item's id so row actions and
            // the summarize/flat views stay consistent with the live list.
            ->selectRaw('inventory_item_snapshots.inventory_item_id as id')
            ->selectRaw("$parentSkuSql as parent_sku")
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('inventory_item_snapshots.sku', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('product_id', 'inventory_item_snapshots.product_id'),
                AllowedFilter::callback('unassigned', function ($query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereNull('inventory_item_snapshots.product_id');
                    }
                }),
                // Product status is a live attribute — there is no historical copy of
                // it — so it reads off the product as it stands today.
                AllowedFilter::callback('product_status', function ($query, $value) {
                    $query->whereHas('product', fn ($p) => $p->whereIn('status', (array) $value));
                }),
                // Applied manually above / consumed by index(); registered so
                // QueryBuilder doesn't reject the keys.
                AllowedFilter::callback('is_active', function () {}),
                AllowedFilter::callback('date', function () {}),
            ])
            ->allowedSorts([
                'id',
                'product_id',
                'sku',
                'is_active',
                'product_name',
                'lead_time',
                'unfulfilled_count',
                'remaining_qty',
                'three_days_average',
                'current_stocks',
                'waiting_for_delivery_stocks',
                'remaining_after_fulfillment',
                'days_it_can_last',
                'po_needed',
                'stocks_needed_for_lead_time',
                'discrepancy',
                AllowedSort::field('created_at', 'item_created_at'),
            ])
            ->defaultSort('item_created_at');
    }

    /**
     * The parent/child roll-up for a past date. Mirrors buildSummaryQuery over the
     * snapshot table: same grouping key, same representative-row rules, and the same
     * non-additive recomputation of po_needed / days_it_can_last from summed parts.
     */
    private function buildSnapshotSummaryQuery(Request $request, Workspace $workspace, string $date): Builder
    {
        $inner = InventoryItemSnapshot::query()
            ->where('inventory_item_snapshots.workspace_id', $workspace->id)
            ->where('inventory_item_snapshots.snapshot_date', $date)
            ->selectRaw('inventory_item_snapshots.inventory_item_id as id, inventory_item_snapshots.parent_id, inventory_item_snapshots.is_parent, inventory_item_snapshots.sku, inventory_item_snapshots.product_id, inventory_item_snapshots.is_active, inventory_item_snapshots.lead_time, inventory_item_snapshots.unfulfilled_count, inventory_item_snapshots.three_days_average, inventory_item_snapshots.item_created_at as created_at, inventory_item_snapshots.product_name, inventory_item_snapshots.product_winning_date, inventory_item_snapshots.current_stocks, inventory_item_snapshots.waiting_for_delivery_stocks, inventory_item_snapshots.discrepancy, inventory_item_snapshots.remaining_after_fulfillment, inventory_item_snapshots.po_needed');

        $this->applySnapshotActiveFilter($request, $inner);
        $this->applySnapshotSummaryVisibility($request, $inner, $workspace);

        if ($search = $request->input('filter.search')) {
            $inner->where('inventory_item_snapshots.sku', 'like', "%{$search}%");
        }

        if ($productId = $request->input('filter.product_id')) {
            $inner->where('inventory_item_snapshots.product_id', $productId);
        }

        if ($productStatus = $request->input('filter.product_status')) {
            $statuses = (array) $productStatus;
            $onStatus = fn ($q) => $q->whereHas('product', fn ($p) => $p->whereIn('status', $statuses));

            $inner->where(fn ($q) => $onStatus($q)->orWhereHas('children', $onStatus));
        }

        if ($request->boolean('filter.unassigned')) {
            $inner->whereNull('inventory_item_snapshots.product_id');
        }

        // Identical group-level formulas to the live roll-up — see buildSummaryQuery
        // for why po_needed and days_it_can_last cannot simply be summed.
        $groupLeadTime = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.lead_time END), MAX(sub.lead_time))';
        $groupCreatedAt = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.created_at END), MIN(sub.created_at))';
        $summedThreeDayAvg = 'SUM(sub.three_days_average)';
        $summedRemaining = 'COALESCE(SUM(sub.remaining_after_fulfillment), 0)';
        $groupStocksNeeded = "($groupLeadTime * $summedThreeDayAvg)";
        $groupPoNeeded = "GREATEST(0, $groupStocksNeeded - $summedRemaining)";
        $groupDaysItCanLast = "(CASE WHEN $summedThreeDayAvg > 0 THEN $summedRemaining / $summedThreeDayAvg ELSE 0 END)";

        $outer = DB::query()
            ->fromSub($inner, 'sub')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.id END), MAX(sub.id)) as id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.sku END), MAX(sub.sku)) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_id END), MAX(sub.product_id)) as product_id')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_name END), MAX(sub.product_name)) as product_name')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_winning_date END), MAX(sub.product_winning_date)) as product_winning_date')
            ->selectRaw('MAX(CASE WHEN sub.is_parent = 1 THEN 1 ELSE 0 END) as is_group')
            ->selectRaw('SUM(CASE WHEN sub.is_parent = 0 THEN 1 ELSE 0 END) as child_count')
            ->selectRaw('MAX(sub.is_active) as is_active')
            ->selectRaw("$groupLeadTime as lead_time")
            ->selectRaw('SUM(sub.unfulfilled_count) as unfulfilled_count')
            ->selectRaw('SUM(sub.current_stocks) as current_stocks')
            ->selectRaw('SUM(sub.waiting_for_delivery_stocks) as waiting_for_delivery_stocks')
            ->selectRaw('SUM(sub.discrepancy) as discrepancy')
            ->selectRaw('NULL as discrepancy_counted_qty')
            ->selectRaw('NULL as discrepancy_date')
            ->selectRaw('SUM(sub.remaining_after_fulfillment) as remaining_after_fulfillment')
            ->selectRaw("$groupStocksNeeded as stocks_needed_for_lead_time")
            ->selectRaw("$groupPoNeeded as po_needed")
            ->selectRaw("$summedThreeDayAvg as three_days_average")
            ->selectRaw("$groupDaysItCanLast as days_it_can_last")
            ->selectRaw("$groupCreatedAt as created_at")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)');

        $sortable = [
            'sku', 'is_active', 'lead_time', 'unfulfilled_count', 'current_stocks',
            'waiting_for_delivery_stocks', 'discrepancy', 'remaining_after_fulfillment',
            'stocks_needed_for_lead_time', 'po_needed', 'three_days_average', 'days_it_can_last',
        ];
        $sort = (string) $request->input('sort');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $outer->orderByRaw("$column ".($descending ? 'DESC' : 'ASC'));
        } else {
            $outer->orderByRaw("$groupCreatedAt ".($descending ? 'DESC' : 'ASC'));
        }

        return $outer;
    }

    /** applyActiveFilter's semantics against the snapshot table's columns. */
    private function applySnapshotActiveFilter(Request $request, $query): void
    {
        $isActiveFilter = $request->input('filter.is_active');

        if ($isActiveFilter === null) {
            $query->where('inventory_item_snapshots.is_active', true);
        } elseif ($isActiveFilter !== 'all') {
            $query->where('inventory_item_snapshots.is_active', filter_var($isActiveFilter, FILTER_VALIDATE_BOOLEAN));
        }
    }

    /** applySummaryVisibility's parent-preserving team scoping, over snapshot rows. */
    private function applySnapshotSummaryVisibility(Request $request, $query, Workspace $workspace): void
    {
        $teamIds = TeamVisibility::scopeTeamIds($request->user(), $workspace);

        if ($teamIds === null) {
            return;
        }

        if (empty($teamIds)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $inTeams = fn ($q) => $q->whereHas('product.shops.teams', fn ($t) => $t->whereIn('teams.id', $teamIds));

        $query->where(fn ($q) => $inTeams($q)->orWhereHas('children', $inTeams));
    }

    /**
     * The snapshot date the list is pinned to, or null for live data. Only a date
     * that actually has rows counts — a date with no snapshot (the job had not run
     * yet, or predates the feature) falls back to live rather than showing an
     * empty list the user cannot explain.
     */
    private function snapshotDate(Request $request, Workspace $workspace): ?string
    {
        $date = $request->input('filter.date');

        if (! $date) {
            return null;
        }

        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        $exists = InventoryItemSnapshot::where('workspace_id', $workspace->id)
            ->where('snapshot_date', $date)
            ->exists();

        return $exists ? $date : null;
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $perPage = (int) $request->input('per_page', 100);
        // The roll-up is the default view; the toggle has to send an explicit 0
        // to get the flat per-SKU list.
        $summarize = $request->boolean('summarize', true);

        // A date filter pins the list to that day's snapshot; without one it reads live.
        $snapshotDate = $this->snapshotDate($request, $workspace);

        if ($snapshotDate) {
            $items = $summarize
                ? $this->buildSnapshotSummaryQuery($request, $workspace, $snapshotDate)->paginate($perPage)->withQueryString()
                : $this->buildSnapshotQuery($request, $workspace, $snapshotDate)->paginate($perPage)->withQueryString();
        } else {
            $items = $summarize
                ? $this->buildSummaryQuery($request, $workspace)->paginate($perPage)->withQueryString()
                : $this->buildQuery($request, $workspace)->paginate($perPage)->withQueryString();
        }

        return Inertia::render('workspaces/inventory/items/index', [
            'items' => $items,
            // Scope the "Select Product" picker to the active team the same way the
            // item list is scoped (product.shops.teams) — a team-scoped user, or
            // anyone viewing as a team, only sees that team's products.
            'products' => Product::where('workspace_id', $workspace->id)
                ->visibleTo($request->user(), $workspace)
                ->get(),
            // Existing parent items, to populate the "group under parent" picker.
            'parents' => InventoryItem::where('workspace_id', $workspace->id)
                ->where('is_parent', true)
                ->orderBy('sku')
                ->get(['id', 'sku']),
            'workspace' => $workspace,
            // The date being shown, or null when the list is live. Resolved server-side
            // so the banner can never claim a snapshot the list did not actually read.
            'snapshotDate' => $snapshotDate,
            // Dates that actually have a snapshot, so the picker can grey out the rest
            // instead of silently falling back to live data.
            'snapshotDates' => InventoryItemSnapshot::where('workspace_id', $workspace->id)
                ->distinct()
                ->orderByDesc('snapshot_date')
                ->limit(365)
                ->pluck('snapshot_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all(),
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'summarize' => $summarize,
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function export(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        // Mirror whatever the list is showing: with the summarize toggle on, export the
        // parent/child roll-up rather than the flat per-SKU rows. Same default as index().
        $summarize = $request->boolean('summarize', true);
        // ...and the same for a pinned date — export the day on screen, not today.
        $snapshotDate = $this->snapshotDate($request, $workspace);

        $filename = 'inventory-items-'.($snapshotDate ?? now()->format('Y-m-d-His')).'.xlsx';

        if ($snapshotDate) {
            $query = $summarize
                ? $this->buildSnapshotSummaryQuery($request, $workspace, $snapshotDate)
                : $this->buildSnapshotQuery($request, $workspace, $snapshotDate);
        } else {
            $query = $summarize
                ? $this->buildSummaryQuery($request, $workspace)
                : $this->buildQuery($request, $workspace);
        }

        return Excel::download(new InventoryItemExport($query, $summarize), $filename);
    }

    public function syncFromGencys(Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        abort_unless($workspace->is_gencys_partner, 403);

        $codes = InventoryUnitCodeItem::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->distinct()
            ->pluck('item_code');

        $created = 0;

        foreach ($codes as $code) {
            // Match on (workspace_id, sku); don't touch product_id on existing
            // items so a manually linked product survives re-syncs. New items get
            // a null product_id from the column default.
            $item = InventoryItem::updateOrCreate(
                ['workspace_id' => $workspace->id, 'sku' => $code],
            );

            if ($item->wasRecentlyCreated) {
                $created++;
            }
        }

        return redirect()
            ->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', "Synced {$created} new inventory item(s) from Gencys.");
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'sku' => 'required|string|max:255|unique:inventory_items,sku,NULL,id,workspace_id,'.$workspace->id,
            'is_active' => 'nullable|boolean',
            'sales_keywords' => 'nullable|array',
            'sales_keywords.*' => 'string|max:255',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
        ]);

        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'product_id' => $request->product_id ?: null,
            'sku' => $request->sku,
            'is_active' => $request->boolean('is_active', true),
            'sales_keywords' => implode(', ', $this->normalizeKeywords($request->input('sales_keywords'))),
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
        ]);

        // back() keeps the list's current filters/sort/page (they live in the URL).
        return redirect()->back()
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_items')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($item->id),
            ],
            'is_active' => 'nullable|boolean',
            'sales_keywords' => 'nullable|array',
            'sales_keywords.*' => 'string|max:255',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
        ]);
        $item->update([
            'product_id' => $request->product_id ?: null,
            'sku' => $request->sku,
            'is_active' => $request->boolean('is_active', true),
            'sales_keywords' => implode(', ', $this->normalizeKeywords($request->input('sales_keywords'))),
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
        ]);

        return redirect()->back()
            ->with('success', 'Inventory Items record updated.');
    }

    /**
     * Update just an item's lead time. Backs the inline lead-time editor on the list,
     * including the summarize view — there the row's id is the group's parent, so an
     * edit sets the parent's lead time, which is exactly the value the roll-up reads
     * for the group's lead_time / stocks_needed_for_lead_time / po_needed.
     */
    public function updateLeadTime(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'lead_time' => 'required|integer|min:0',
        ]);

        $item->update(['lead_time' => $validated['lead_time']]);

        return redirect()->back()->with('success', 'Lead time updated.');
    }

    /**
     * The item's ledger stock as of a date: the remaining_qty of the latest transaction
     * dated on or before $date. Null when there's no transaction that early. This is the
     * reference a physical count is measured against, so a backdated count compares to the
     * stock as it stood then — not today's.
     */
    private function ledgerStockAsOf(InventoryItem $item, string $date): ?int
    {
        $qty = $item->transactions()
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('remaining_qty');

        return $qty === null ? null : (int) $qty;
    }

    /**
     * The ledger stock as of a date, for the Adjust Count modal to show what the system
     * thought the count was on the chosen date (and preview the resulting discrepancy).
     */
    public function stockAsOf(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('View Inventory Items', $workspace);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        return response()->json([
            'remaining_qty' => $this->ledgerStockAsOf($item, $validated['date']),
        ]);
    }

    /**
     * The purchase orders behind an item's "Waiting for Delivery" figure: one row per
     * order line still owing stock, newest issue date first. A parent fans out to its
     * children's lines, matching the summary view's rolled-up total.
     */
    public function pendingPurchaseOrders(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('View Inventory Items', $workspace);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $itemIds = $item->is_parent
            ? InventoryItem::where('parent_id', $item->id)->pluck('id')->push($item->id)->all()
            : [$item->id];

        $lines = PurchasedOrderItem::query()
            ->whereIn('inventory_item_id', $itemIds)
            ->whereHas('purchasedOrder', fn ($q) => $q->whereIn('status', PurchasedOrder::AWAITING_DELIVERY_STATUSES))
            ->with(['purchasedOrder', 'inventoryItem:id,sku'])
            ->withSum('deliveries as delivered_qty', 'qty')
            ->get()
            ->filter(fn (PurchasedOrderItem $line) => $line->balance > 0)
            ->sortByDesc(fn (PurchasedOrderItem $line) => $line->purchasedOrder->issue_date)
            ->values()
            ->map(fn (PurchasedOrderItem $line) => [
                'id' => $line->purchasedOrder->id,
                'sku' => $line->inventoryItem?->sku,
                'control_no' => $line->purchasedOrder->control_no,
                'cust_po_no' => $line->purchasedOrder->cust_po_no,
                'issue_date' => $line->purchasedOrder->issue_date?->toDateString(),
                'expected_delivery_date' => $line->purchasedOrder->expected_delivery_date?->toDateString(),
                'status' => $line->purchasedOrder->status,
                'status_label' => $line->purchasedOrder->status_label,
                'delivery_timeliness' => $line->purchasedOrder->delivery_timeliness,
                'ordered_qty' => (int) $line->count,
                'delivered_qty' => $line->delivered_qty,
                'balance' => $line->balance,
            ]);

        return response()->json([
            'orders' => $lines,
            'total_balance' => $lines->sum('balance'),
        ]);
    }

    /**
     * Record a physical stock count for an item. The user types the counted quantity; we
     * store the signed discrepancy against the ledger stock as of the count date (see
     * ledgerStockAsOf()). buildQuery() then layers the latest discrepancy onto the item's
     * current stock, so the offset found on the count date carries forward.
     */
    public function adjustCount(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
            ],
            'counted_qty' => 'required|integer|min:0',
        ]);

        // Measure against the stock as it stood on the count date (0 if none that early).
        $ledgerQty = $this->ledgerStockAsOf($item, $validated['date']) ?? 0;

        $item->discrepancies()->create([
            'workspace_id' => $workspace->id,
            'date' => $validated['date'],
            'counted_qty' => (int) $validated['counted_qty'],
            'discrepancy' => (int) $validated['counted_qty'] - $ledgerQty,
        ]);

        return redirect()->back()
            ->with('success', 'Stock count recorded.');
    }

    /**
     * Activate or deactivate multiple inventory items at once.
     */
    public function bulkUpdateStatus(Request $request, Workspace $workspace)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'is_active' => 'required|boolean',
        ]);

        $updated = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['is_active' => $validated['is_active']]);

        $status = $validated['is_active'] ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "{$updated} inventory item(s) {$status}.");
    }

    /**
     * Assign (or clear) the linked product on multiple inventory items at once.
     * A null product_id unlinks the product from the selected items.
     */
    public function bulkUpdateProduct(Request $request, Workspace $workspace)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $productId = $validated['product_id'] ?? null;

        $updated = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['product_id' => $productId]);

        $message = $productId
            ? "Product set for {$updated} inventory item(s)."
            : "Product cleared for {$updated} inventory item(s).";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Group selected items under a parent placeholder item, so SKU variants of the
     * same product (e.g. from different suppliers) roll up together in the summary
     * view. Assigns to an existing parent (`parent_id`), creates one from
     * `new_parent_sku`, or — when neither is given — ungroups the selected items
     * (clears their parent_id). Parent placeholders are never fed to n8n.
     */
    public function bulkGroup(Request $request, Workspace $workspace)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($q) => $q
                    ->where('workspace_id', $workspace->id)
                    ->where('is_parent', true)),
            ],
            'new_parent_sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('inventory_items', 'sku')->where('workspace_id', $workspace->id),
            ],
        ]);

        $parentId = $validated['parent_id'] ?? null;

        if (! $parentId && ! empty($validated['new_parent_sku'])) {
            $parent = InventoryItem::create([
                'workspace_id' => $workspace->id,
                'sku' => trim($validated['new_parent_sku']),
                'is_parent' => true,
                'is_active' => true,
            ]);
            $parentId = $parent->id;
        }

        // Never parent an item to itself, and never nest one parent under another —
        // only leaf items (is_parent = false) can be grouped.
        $ids = collect($validated['ids'])
            ->reject(fn ($id) => $id === $parentId)
            ->all();

        // Ungrouping a parent means "break this group up". The summary view lists a
        // group under its parent's id, so that is the id the client sends — and the
        // update below only touches leaf rows, so on its own it would report
        // "0 item(s) ungrouped" and leave the group intact. Expand any selected
        // parent into its children first.
        if (! $parentId) {
            $childIds = InventoryItem::where('workspace_id', $workspace->id)
                ->whereIn('parent_id', $ids)
                ->pluck('id')
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $childIds)));
        }

        $updated = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $ids)
            ->where('is_parent', false)
            ->update(['parent_id' => $parentId]);

        $message = $parentId
            ? "{$updated} item(s) grouped under the parent item."
            : "{$updated} item(s) ungrouped.";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Split a comma-separated keyword string into a clean array:
     * trim, drop blanks, de-duplicate.
     *
     * @param  mixed  $keywords
     * @return string[]
     */
    private function normalizeKeywords($keywords): array
    {
        $list = is_array($keywords)
            ? $keywords
            : preg_split('/[,\n]+/', (string) $keywords);

        return collect($list)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function destroy(Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Delete Inventory Items', $workspace);

        $item->delete();

        return redirect()->back()
            ->with('success', 'Inventory Items record deleted.');
    }
}
