<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemReportExport;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Support\InventoryItemMetrics;
use Modules\Inventory\Support\InventoryItemSnapshotter;
use Modules\Inventory\Support\InventoryStockColumns;
use Modules\Inventory\Support\ItemReportFacts;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    use AuthorizesRequests;

    /**
     * The correlated-subquery SQL fragments for an item's computed stock columns,
     * all keyed off `inventory_items.id`. Deliberately delegated to
     * InventoryItemMetrics rather than spelled out here: the daily snapshot command
     * reads through the same fragments, and a private copy in this controller is
     * exactly how the snapshot once drifted out of step with the list it photographs.
     *
     * @return array<string, string>
     */
    private function stockSql(): array
    {
        return InventoryItemMetrics::sql();
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

        // The computed columns read from derived tables; attach them once here.
        InventoryStockColumns::applyJoins($base);

        return QueryBuilder::for($base)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            ->selectRaw("$parentSkuSql as parent_sku")
            // Undelivered remainder on orders a supplier already has.
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
            ->tap(fn ($q) => InventoryStockColumns::applyJoins($q))
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.product_id, inventory_items.is_active, inventory_items.lead_time, inventory_items.days_of_coverage, inventory_items.unfulfilled_count, inventory_items.three_days_average, inventory_items.created_at, products.name as product_name, products.winning_date as product_winning_date')
            // The group's identity, carried on every row of the group and read
            // straight off the parent rather than off whichever rows survived the
            // filters. A search or a product filter can exclude the parent
            // placeholder, and the roll-up would then take the group's name — and
            // worse, its id — from an arbitrary child, so an inline edit would
            // patch a different item than the one on screen.
            ->selectRaw('COALESCE((SELECT p.sku FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.sku) as group_sku')
            // The parent's own product, never the children's. A group whose
            // parent has no product shows none: one of its children's would be an
            // arbitrary pick dressed up as the group's.
            ->selectRaw('(SELECT gp.name FROM products gp WHERE gp.id = COALESCE((SELECT p.product_id FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.product_id)) as group_product_name')
            ->selectRaw('(SELECT gp.winning_date FROM products gp WHERE gp.id = COALESCE((SELECT p.product_id FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.product_id)) as group_product_winning_date')
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
            // The group key itself, so the row's id is the parent's whether or
            // not the parent row survived the filters — every action the list
            // offers targets this id.
            ->selectRaw('COALESCE(sub.parent_id, sub.id) as id')
            ->selectRaw('MAX(sub.group_sku) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_id END), MAX(sub.product_id)) as product_id')
            ->selectRaw('MAX(sub.group_product_name) as product_name')
            ->selectRaw('MAX(sub.group_product_winning_date) as product_winning_date')
            // Having a parent is what makes a row a group, not whether the parent
            // placeholder happened to match the filters.
            ->selectRaw('MAX(sub.parent_id IS NOT NULL) as is_group')
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
     * Left-join the day's snapshot row onto an inventory_items query, as `snap`.
     *
     * Left rather than inner on purpose: an item with no row for that day — one
     * created or synced after the snapshot ran — has to survive the join and show
     * "—" in its metric columns rather than drop out of the list entirely.
     */
    private function joinSnapshot($query, Workspace $workspace, string $date): void
    {
        $query->leftJoin('inventory_item_snapshots as snap', function ($join) use ($workspace, $date) {
            $join->on('snap.inventory_item_id', '=', 'inventory_items.id')
                ->where('snap.workspace_id', $workspace->id)
                ->where('snap.snapshot_date', $date);
        });
    }

    /**
     * The measured columns a snapshot froze, read from the snapshot row alone.
     *
     * Never coalesced to the item's live value: a row with no snapshot for the
     * day has no figure for that day, and today's number shown under a past date
     * would be history the workspace never recorded. NULL reaches the list as "—".
     */
    private function snapshotMetricColumns(): array
    {
        return [
            'unfulfilled_count',
            'unfulfilled_count_orders',
            'three_days_average',
            'three_days_average_orders',
            'remaining_qty',
            'current_stocks',
            'waiting_for_delivery_stocks',
            'requested_stocks',
            'discrepancy',
            'discrepancy_counted_qty',
            'discrepancy_date',
            'remaining_after_fulfillment',
            'stocks_needed_for_lead_time',
            'po_qty',
            'po_needed',
            'days_it_can_last',
            'demand_as_of',
            ...ItemReportFacts::SNAPSHOT_COLUMNS,
        ];
    }

    /**
     * The flat list for a snapshot date — every saved item, with that day's
     * figures joined on.
     *
     * Anchored on inventory_items rather than on the snapshot table. Reading the
     * snapshot table directly listed only the items that had a row for the day,
     * so anything created or synced since the last run was invisible until the
     * next one — a fresh ERP sync looked like it had done nothing. Now the items
     * are the list and the snapshot supplies the numbers; an item without a row
     * for the day shows "—" across its metric columns.
     *
     * Identity (SKU, product, active) and the editable config come from the item,
     * so a row says what an edit to it would change. The metrics stay frozen as
     * the snapshot recorded them.
     */
    private function buildSnapshotQuery(Request $request, Workspace $workspace, string $date): QueryBuilder
    {
        $base = InventoryItem::where('inventory_items.workspace_id', $workspace->id)
            // Same as the live list: parents carry no stock of their own and would
            // show as an empty row here.
            ->where('inventory_items.is_parent', false)
            ->visibleTo($request->user(), $workspace);

        $this->applyActiveFilter($request, $base);
        $this->joinSnapshot($base, $workspace, $date);

        // Parent's SKU for child rows, so the flat list can show what each SKU is
        // grouped under. NULL for parents and standalone items.
        $parentSkuSql = '(SELECT p.sku FROM inventory_items p WHERE p.id = inventory_items.parent_id)';

        return QueryBuilder::for($base)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
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
                'inventory_items.days_of_coverage',
                'inventory_items.created_at',
            ])
            ->with(['product'])
            ->selectRaw("$parentSkuSql as parent_sku")
            // Lead time is configuration, not a measurement: show the day's frozen
            // value when there is one, else the item's own, so the inline editor
            // always has a number to edit rather than a dash.
            ->selectRaw('COALESCE(snap.lead_time, inventory_items.lead_time) as lead_time')
            ->selectRaw(implode(', ', array_map(
                fn (string $column) => "snap.$column as $column",
                $this->snapshotMetricColumns(),
            )))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('inventory_items.sku', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('product_id', 'inventory_items.product_id'),
                AllowedFilter::callback('unassigned', function ($query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereNull('inventory_items.product_id');
                    }
                }),
                AllowedFilter::callback('product_status', function ($query, $value) {
                    $query->whereIn('products.status', (array) $value);
                }),
                // Applied manually above / consumed by index(); registered so
                // QueryBuilder doesn't reject the keys.
                AllowedFilter::callback('is_active', function () {}),
                AllowedFilter::callback('date', function () {}),
            ])
            // Every sort is table-qualified: both sides of the join carry columns
            // of the same name, and a bare one is ambiguous to MySQL.
            ->allowedSorts([
                AllowedSort::field('id', 'inventory_items.id'),
                AllowedSort::field('product_id', 'inventory_items.product_id'),
                AllowedSort::field('sku', 'inventory_items.sku'),
                AllowedSort::field('is_active', 'inventory_items.is_active'),
                AllowedSort::field('product_name', 'products.name'),
                AllowedSort::callback('lead_time', function ($query, $descending) {
                    $query->orderByRaw('COALESCE(snap.lead_time, inventory_items.lead_time) '.($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::field('unfulfilled_count', 'snap.unfulfilled_count'),
                AllowedSort::field('remaining_qty', 'snap.remaining_qty'),
                AllowedSort::field('three_days_average', 'snap.three_days_average'),
                AllowedSort::field('current_stocks', 'snap.current_stocks'),
                AllowedSort::field('waiting_for_delivery_stocks', 'snap.waiting_for_delivery_stocks'),
                AllowedSort::field('remaining_after_fulfillment', 'snap.remaining_after_fulfillment'),
                AllowedSort::field('days_it_can_last', 'snap.days_it_can_last'),
                AllowedSort::field('po_needed', 'snap.po_needed'),
                AllowedSort::field('stocks_needed_for_lead_time', 'snap.stocks_needed_for_lead_time'),
                AllowedSort::field('po_qty', 'snap.po_qty'),
                AllowedSort::field('discrepancy', 'snap.discrepancy'),
                AllowedSort::field('created_at', 'inventory_items.created_at'),
            ])
            ->defaultSort('created_at');
    }

    /**
     * The parent/child roll-up for a snapshot date. Same anchoring as
     * buildSnapshotQuery — every saved item is grouped, the day's snapshot rows
     * supply the figures — over the live grouping, so an item synced today rolls
     * into the parent it is under today rather than vanishing.
     *
     * A group whose items have no rows for the day sums to NULL throughout, which
     * the list renders as "—" rather than as a confident zero.
     *
     * @param  string  $basis  'unit' or 'order' — see demandBasis(). Defaults to
     *                         units, so the export keeps reading the figures it
     *                         always has whatever the list is set to.
     */
    private function buildSnapshotSummaryQuery(Request $request, Workspace $workspace, string $date, string $basis = self::BASIS_UNIT): Builder
    {
        // Per-item rows for the whole workspace (parents included, so their
        // children roll into them).
        $inner = InventoryItem::query()
            ->where('inventory_items.workspace_id', $workspace->id)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.product_id, inventory_items.is_active, inventory_items.created_at, products.name as product_name, products.winning_date as product_winning_date')
            // Configuration: the day's frozen value when it recorded one, else the
            // item's own — the group formulas below need a number either way.
            ->selectRaw('COALESCE(snap.lead_time, inventory_items.lead_time) as lead_time')
            ->selectRaw('COALESCE(snap.days_of_coverage, inventory_items.days_of_coverage) as days_of_coverage')
            // Measurements: the snapshot's alone. NULL where the day has no row.
            ->selectRaw('snap.unfulfilled_count as unfulfilled_count')
            ->selectRaw('snap.three_days_average as three_days_average')
            ->selectRaw('snap.current_stocks as current_stocks')
            ->selectRaw('snap.waiting_for_delivery_stocks as waiting_for_delivery_stocks')
            ->selectRaw('snap.discrepancy as discrepancy')
            ->selectRaw('snap.remaining_after_fulfillment as remaining_after_fulfillment')
            // The two demand figures in orders, frozen beside the unit ones.
            // The roll-up sums whichever pair the basis asks for.
            ->selectRaw('snap.three_days_average_orders as three_days_average_orders')
            ->selectRaw('snap.unfulfilled_count_orders as unfulfilled_count_orders')
            ->selectRaw('snap.po_needed as po_needed')
            // The group's identity, carried on every row of the group and read
            // straight off the parent rather than off whichever rows survived the
            // filters — see buildSummaryQuery for why that matters.
            ->selectRaw('COALESCE((SELECT p.sku FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.sku) as group_sku')
            ->selectRaw('(SELECT gp.name FROM products gp WHERE gp.id = COALESCE((SELECT p.product_id FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.product_id)) as group_product_name')
            ->selectRaw('(SELECT gp.winning_date FROM products gp WHERE gp.id = COALESCE((SELECT p.product_id FROM inventory_items p WHERE p.id = inventory_items.parent_id), inventory_items.product_id)) as group_product_winning_date')
            // The report's frozen group figures, carried through so the roll-up
            // below can hand them back untouched.
            ->selectRaw(implode(', ', array_map(
                fn (string $column) => "snap.$column as $column",
                ItemReportFacts::SNAPSHOT_COLUMNS,
            )));

        $this->joinSnapshot($inner, $workspace, $date);
        $this->applyActiveFilter($request, $inner);
        $this->applySummaryVisibility($request, $inner, $workspace);

        if ($search = $request->input('filter.search')) {
            $inner->where('inventory_items.sku', 'like', "%{$search}%");
        }

        if ($productId = $request->input('filter.product_id')) {
            $inner->where('inventory_items.product_id', $productId);
        }

        // Keeps the parent placeholder whenever any of its children match — a
        // parent has no product of its own, and filtering it out would strip the
        // group of the row that supplies its SKU and lead time.
        if ($productStatus = $request->input('filter.product_status')) {
            $statuses = (array) $productStatus;
            $onStatus = fn ($q) => $q->whereHas('product', fn ($p) => $p->whereIn('status', $statuses));

            $inner->where(fn ($q) => $onStatus($q)->orWhereHas('children', $onStatus));
        }

        if ($request->boolean('filter.unassigned')) {
            $inner->whereNull('inventory_items.product_id');
        }

        // Identical group-level formulas to the live roll-up — see buildSummaryQuery
        // for why po_needed and days_it_can_last cannot simply be summed.
        $groupLeadTime = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.lead_time END), MAX(sub.lead_time))';
        $groupDaysOfCoverage = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.days_of_coverage END), MAX(sub.days_of_coverage))';
        $groupCreatedAt = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.created_at END), MIN(sub.created_at))';

        // Two rates, and the difference between them is the whole design.
        //
        // The DISPLAYED rate follows the basis: units a day, or the orders that
        // carried them. Same for Unfulfilled. Those two are demand, and demand is
        // what someone switching to orders wants to read.
        //
        // The PLANNING rate is always units, because everything it touches is:
        // stock is counted in units, purchase orders are raised in units, and
        // Remaining is a unit figure. Measuring unit stock against an order rate
        // would buy a bundled SKU short by its bundle size — a group shipping 1.5
        // units an order would plan for two thirds of what it actually consumes
        // and quietly run out. So Stocks Needed, PO QTY, PO Needed and cover read
        // the unit rate in both bases and do not move when the toggle does.
        $orderBasis = $basis === self::BASIS_ORDER;
        $inBasis = fn (string $column) => $orderBasis ? $column.'_orders' : $column;

        $displayedAverage = 'SUM(sub.'.$inBasis('three_days_average').')';
        $planAverage = 'SUM(sub.three_days_average)';
        $remainingOut = 'SUM(sub.remaining_after_fulfillment)';

        // Coalesced to 0 for the arithmetic only. The displayed column keeps its
        // NULL, which means "the day recorded nothing", but a NULL here would
        // swallow the whole PO Needed expression and report nothing to buy for a
        // group that has stock and no demand.
        $summedRemaining = "COALESCE($remainingOut, 0)";
        $groupStocksNeeded = "($groupLeadTime * $planAverage)";
        $groupCoverageBuffer = "($groupDaysOfCoverage * $planAverage)";
        $groupPoNeeded = "GREATEST(0, $groupCoverageBuffer + $groupStocksNeeded - $summedRemaining)";
        // The NULL arm is what separates "the day recorded nothing for this group"
        // from "it sold nothing that day": without it a group with no snapshot
        // rows would report 0 days of cover, which reads as an emergency.
        $groupDaysItCanLast = "(CASE WHEN $planAverage IS NULL THEN NULL WHEN $planAverage > 0 THEN $summedRemaining / $planAverage ELSE 0 END)";

        $outer = DB::query()
            ->fromSub($inner, 'sub')
            // The group key itself, so the row's id is the parent's whether or
            // not the parent row survived the filters — every action the list
            // offers targets this id.
            ->selectRaw('COALESCE(sub.parent_id, sub.id) as id')
            ->selectRaw('MAX(sub.group_sku) as sku')
            ->selectRaw('COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.product_id END), MAX(sub.product_id)) as product_id')
            ->selectRaw('MAX(sub.group_product_name) as product_name')
            ->selectRaw('MAX(sub.group_product_winning_date) as product_winning_date')
            // Having a parent is what makes a row a group, not whether the parent
            // placeholder happened to match the filters.
            ->selectRaw('MAX(sub.parent_id IS NOT NULL) as is_group')
            ->selectRaw('SUM(CASE WHEN sub.is_parent = 0 THEN 1 ELSE 0 END) as child_count')
            ->selectRaw('MAX(sub.is_active) as is_active')
            ->selectRaw("$groupLeadTime as lead_time")
            ->selectRaw('SUM(sub.'.$inBasis('unfulfilled_count').') as unfulfilled_count')
            // Stock stays in units in both bases: it is counted on a shelf and
            // bought in units, and the reorder plan below reads it that way.
            ->selectRaw('SUM(sub.current_stocks) as current_stocks')
            ->selectRaw('SUM(sub.waiting_for_delivery_stocks) as waiting_for_delivery_stocks')
            ->selectRaw('SUM(sub.discrepancy) as discrepancy')
            ->selectRaw('NULL as discrepancy_counted_qty')
            ->selectRaw('NULL as discrepancy_date')
            ->selectRaw("$remainingOut as remaining_after_fulfillment")
            ->selectRaw("$groupStocksNeeded as stocks_needed_for_lead_time")
            ->selectRaw("$groupCoverageBuffer as po_qty")
            ->selectRaw("$groupPoNeeded as po_needed")
            ->selectRaw("$displayedAverage as three_days_average")
            ->selectRaw("$groupDaysItCanLast as days_it_can_last")
            ->selectRaw("$groupCreatedAt as created_at")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)');

        // MAX rather than SUM: these were group figures when they were frozen and
        // are identical across the group's rows, so MAX returns them exactly.
        // Summing would multiply each by the number of SKUs in the group.
        foreach (ItemReportFacts::SNAPSHOT_COLUMNS as $column) {
            $outer->selectRaw("MAX(sub.$column) as $column");
        }

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
     * How the reorder maths is denominated: in units of stock, or in the orders
     * that stock ships against.
     *
     * The demand feed counts both. An order line names a unit code, which the
     * snapshot expands into its component items — so one order for a three-item
     * bundle is one order and three units. `units_3d / 3` is exactly the group's
     * summed three_days_average; `orders_3d / 3` is the same three days counted
     * as distinct orders instead.
     *
     * The order basis converts BOTH sides. Demand becomes orders a day, and the
     * stock it is measured against becomes orders' worth of stock — units
     * divided by the units-per-order those same three days observed. Converting
     * only the demand would read one side in orders and the other in units, and
     * on a bundled SKU that understates what to buy by the bundle size: a group
     * shipping 1.5 units per order would plan for two thirds of the stock it
     * actually consumes and quietly run out.
     *
     * Because both sides convert, days of cover comes out identical either way.
     * That is the check, not a shortcoming — the toggle changes the unit of
     * account, not the decision. What it does change is what the buyer reads:
     * "enough for 1,804 more orders" instead of "2,706 units".
     */
    private const BASIS_UNIT = 'unit';

    private const BASIS_ORDER = 'order';

    /**
     * The basis the list should use, honoured only where it can be.
     *
     * Two hard limits, both from the data rather than from taste. orders_3d is a
     * GROUP figure stamped on every row of the group, so it only means anything
     * once the rows are rolled up — on the flat per-SKU list every sibling would
     * repeat the group's order count as though it were its own. And it only
     * exists on snapshot rows, so a workspace reading live has nothing to switch
     * to. Either way this falls back to units rather than showing a toggle that
     * silently lies.
     */
    private function demandBasis(Request $request, bool $summarize, ?string $snapshotDate): string
    {
        if (! $summarize || $snapshotDate === null) {
            return self::BASIS_UNIT;
        }

        return $request->input('basis') === self::BASIS_ORDER
            ? self::BASIS_ORDER
            : self::BASIS_UNIT;
    }

    /**
     * Whether this workspace's item figures come from frozen snapshots.
     *
     * Only Gencys partners. Their stock, purchase orders and demand all arrive by
     * batch sync, so a frozen day is as current as the data ever gets and freezing
     * it buys a consistent page. Everyone else keeps their inventory in Artemis
     * directly — a receipt or an adjustment saved a minute ago is the truth — and
     * reading yesterday's photograph would show them numbers their own edit
     * already changed. The daily command snapshots every workspace regardless, so
     * a non-partner does have rows; they are simply not what the page should read.
     */
    private function usesSnapshots(Workspace $workspace): bool
    {
        return (bool) $workspace->is_gencys_partner;
    }

    /**
     * The snapshot date the list is pinned to, or null for live data. Only a date
     * that actually has rows counts — a date with no snapshot (the job had not run
     * yet, or predates the feature) falls back to live rather than showing an
     * empty list the user cannot explain.
     */
    private function snapshotDate(Request $request, Workspace $workspace): ?string
    {
        if (! $this->usesSnapshots($workspace)) {
            return null;
        }

        $date = $request->input('filter.date');

        if (! $date) {
            // No date asked for: the newest day we hold. The list is a snapshot
            // view now rather than a live one — everything it shows arrives by
            // batch sync anyway, so computing it per request only ever bought a
            // fresher-looking copy of the same figures at page-load cost. Null
            // here means the workspace has no snapshot at all, and the caller
            // falls back to computing live so a new workspace is not blank.
            return InventoryItemSnapshot::where('workspace_id', $workspace->id)
                ->max('snapshot_date');
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

        // Which frozen day the list shows: the one asked for, or the newest.
        // A workspace that reads live has no days to pick between, so a stray
        // `filter[date]` on the URL is ignored rather than treated as a miss.
        $usesSnapshots = $this->usesSnapshots($workspace);
        $requestedDate = $usesSnapshots ? $request->input('filter.date') : null;
        $snapshotDate = $this->snapshotDate($request, $workspace);

        // Units or orders. Resolved before the query so the page and the rows it
        // renders can never disagree about which one is on screen.
        $basis = $this->demandBasis($request, $summarize, $snapshotDate);

        if ($snapshotDate) {
            $items = $summarize
                ? $this->buildSnapshotSummaryQuery($request, $workspace, $snapshotDate, $basis)->paginate($perPage)->withQueryString()
                : $this->buildSnapshotQuery($request, $workspace, $snapshotDate)->paginate($perPage)->withQueryString();
        } elseif ($requestedDate) {
            // A specific day was asked for and nothing was recorded for it.
            // Falling back to live figures would answer for today while the page
            // claims to be showing the day someone picked — so show nothing and
            // let the page say why.
            $items = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } else {
            // Either a workspace that reads live by policy (see usesSnapshots),
            // or a partner that has never been snapshotted — one that has just
            // been set up rather than a missing day, where computing live keeps
            // it from looking broken until the first scheduled run lands.
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
            // The day being shown. Resolved server-side so the picker can never
            // claim a snapshot the list did not actually read. Null only for a
            // workspace that has never been snapshotted, which falls back to live.
            'snapshotDate' => $snapshotDate,
            // What was asked for, whether or not it exists. An empty list needs
            // to name the day it found nothing for.
            'requestedDate' => $requestedDate,
            // When that day's rows were last written. The snapshot is refreshed
            // several times a day and again whenever someone edits a field, so
            // the date alone does not say how current the page is.
            'snapshotUpdatedAt' => $snapshotDate
                ? InventoryItemSnapshot::where('workspace_id', $workspace->id)
                    ->where('snapshot_date', $snapshotDate)
                    ->max('updated_at')
                : null,
            // Dates that actually have a snapshot, so the picker can grey out the rest
            // instead of silently falling back to live data. Empty for a workspace
            // that reads live, which has no frozen days to offer even though the
            // daily command wrote rows for it.
            'snapshotDates' => $usesSnapshots
                ? InventoryItemSnapshot::where('workspace_id', $workspace->id)
                    ->distinct()
                    ->orderByDesc('snapshot_date')
                    ->limit(365)
                    ->pluck('snapshot_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->all()
                : [],
            // Whether the order basis is offered at all, and whether it is on.
            // Both come from the server because both are decided there: the
            // toggle is only meaningful on a rolled-up snapshot, and a client
            // that assumed otherwise would show "orders" over unit figures.
            'basis' => $basis,
            'basisAvailable' => $summarize && $snapshotDate !== null,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'summarize' => $summarize,
                'basis' => $basis,
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Download the items as a spreadsheet.
     *
     * One export, carrying every column the report defines rather than only the
     * ones the table happens to show — a download is read away from the app, so
     * a column someone forgot to switch on is a column they cannot get back
     * without asking for another file.
     *
     * Always rolled up to the group: a parent and its children share one reorder
     * decision, and the demand figures are the group's, so per-SKU rows would
     * split one decision across several lines and invite double-counting.
     *
     * Follows whichever day the list is showing. That day's rows already carry
     * the report figures, frozen when they were written — recomputing them from
     * today's feeds would answer a different question while looking like history.
     */
    public function export(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $snapshotDate = $this->snapshotDate($request, $workspace);

        $export = $snapshotDate
            ? InventoryItemReportExport::asOf($this->buildSnapshotSummaryQuery($request, $workspace, $snapshotDate))
            : InventoryItemReportExport::live($this->buildSummaryQuery($request, $workspace), new ItemReportFacts($workspace));

        return Excel::download(
            $export,
            'inventory-items-'.($snapshotDate ?? now()->format('Y-m-d-His')).'.xlsx',
        );
    }

    public function syncFromErp(Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        abort_unless($workspace->is_gencys_partner, 403);

        $webhookUrl = config('services.n8n.gencys_inventory_items_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return back()->with('error', 'Gencys ERP item sync is not configured yet. Please contact support.');
        }

        if (blank($workspace->erp_username) || blank($workspace->erp_password)) {
            return back()->with('error', 'This workspace is not connected to the ERP. Add ERP credentials and an API key first.');
        }

        $count = 0;

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'erp_username' => $workspace->erp_username,
                'erp_password' => $workspace->erp_password,
            ])->throw();

            $data = $response->json();

            foreach ($data as $item) {
                InventoryItem::updateOrCreate([
                    'workspace_id' => $workspace->id,
                    'sku' => $item['name'],
                ], [
                    'reference_id' => $item['id'],
                ]);

                $count++;
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reach the sync service. Please try again.'."\n".$e->getMessage());
        }

        return back()->with('success', "Synced {$count} inventory item(s) from Gencys ERP.");
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

        // The list reads today's snapshot, so an edit that is not written back
        // into it would appear to do nothing until the next scheduled run.
        // Lead time feeds stocks_needed_for_lead_time and po_needed, both of
        // which the list shows.
        $this->refreshTodaysSnapshot($workspace, $item);

        return redirect()->back()->with('success', 'Lead time updated.');
    }

    /**
     * Rewrite today's snapshot rows for an item's group, so an edit shows up
     * immediately instead of waiting for the next scheduled run.
     *
     * Two deliberate limits. It refreshes the whole group, not the one row
     * edited, because the roll-up derives the group's figures from every row in
     * it — rewriting one and leaving its siblings stale would show a group half
     * from before the edit and half from after.
     *
     * And it only ever updates a day that already exists. Writing today's row
     * when today has not been snapshotted would make it the newest day, and the
     * list reads the newest day — so a single edit would replace the whole list
     * with the one group it touched. With no row for today the list is reading
     * an older day anyway, and the next scheduled run is what moves it on.
     *
     * Past days are never touched: a snapshot is what was true then, and editing
     * a field now does not change what was true then.
     */
    private function refreshTodaysSnapshot(Workspace $workspace, InventoryItem $item): void
    {
        // A live workspace has no snapshot to keep in step, and any row it still
        // carries from before the partner rule is one nothing reads.
        if (! $this->usesSnapshots($workspace)) {
            return;
        }

        $today = Carbon::today()->toDateString();

        $exists = InventoryItemSnapshot::where('workspace_id', $workspace->id)
            ->where('snapshot_date', $today)
            ->exists();

        if (! $exists) {
            return;
        }

        $groupId = (int) ($item->parent_id ?? $item->id);

        $ids = InventoryItem::where('workspace_id', $workspace->id)
            ->where(fn ($q) => $q->whereKey($groupId)->orWhere('parent_id', $groupId))
            ->pluck('id')
            ->all();

        (new InventoryItemSnapshotter($workspace, $today))->refresh($ids);
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
            // Split the same way the list columns are: the released subtotal is
            // what "Waiting for Delivery" shows, the requested subtotal is what
            // the reorder maths deliberately ignores. The modal still lists both
            // — hiding an order because it is stuck in approval is how it stays
            // stuck.
            'released_balance' => $lines
                ->whereIn('status', PurchasedOrder::RELEASED_STATUSES)
                ->sum('balance'),
            'requested_balance' => $lines
                ->whereIn('status', PurchasedOrder::REQUESTED_STATUSES)
                ->sum('balance'),
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
