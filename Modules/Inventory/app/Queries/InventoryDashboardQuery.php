<?php

namespace Modules\Inventory\Queries;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Support\InventoryStockColumns;

/**
 * Assembles every figure the Inventory dashboard renders. Kept out of the
 * controller per the module convention ("don't inline aggregations") so the
 * heavy SQL lives in one testable place.
 *
 * Stock-derived numbers reuse InventoryStockColumns, so KPI tiles and the
 * stock-health table agree with the Inventory Items list to the unit.
 */
class InventoryDashboardQuery
{
    private CarbonImmutable $start;

    private CarbonImmutable $end;

    public function __construct(
        private readonly Workspace $workspace,
        ?string $start = null,
        ?string $end = null,
    ) {
        // Default window: the trailing 30 days, inclusive of today.
        $this->end = $end ? CarbonImmutable::parse($end)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $this->start = $start
            ? CarbonImmutable::parse($start)->startOfDay()
            : $this->end->subDays(29)->startOfDay();
    }

    /**
     * A subquery of active items with the derived stock columns selected, so the
     * KPI aggregates and the stock-health table read from one definition.
     */
    private function stockBase(bool $createdInRange = false): Builder
    {
        return DB::table('inventory_items')
            ->where('inventory_items.workspace_id', $this->workspace->id)
            ->where('inventory_items.is_active', true)
            ->when($createdInRange, fn ($q) => $q->whereBetween(
                'inventory_items.created_at',
                [$this->start->toDateTimeString(), $this->end->toDateTimeString()],
            ))
            ->selectRaw('inventory_items.id')
            ->selectRaw('inventory_items.sku')
            ->selectRaw('inventory_items.product_id')
            ->selectRaw('inventory_items.lead_time')
            ->selectRaw('inventory_items.three_days_average')
            ->selectRaw(InventoryStockColumns::currentStocks().' as current_stocks')
            ->selectRaw(InventoryStockColumns::waitingStocks().' as waiting_for_delivery_stocks')
            ->selectRaw(InventoryStockColumns::remainingAfterFulfillment().' as remaining_after_fulfillment')
            ->selectRaw(InventoryStockColumns::poNeeded().' as po_needed')
            ->selectRaw(InventoryStockColumns::daysItCanLast().' as days_it_can_last');
    }

    /** Headline KPI tiles, scoped to records created within the selected window. */
    public function kpis(): array
    {
        // Item-based tiles count only SKUs created in the window.
        $stock = DB::query()->fromSub($this->stockBase(true), 's');

        $agg = (clone $stock)->selectRaw(
            'COUNT(*) as active_skus,
             COALESCE(SUM(current_stocks), 0) as total_stock_on_hand,
             COALESCE(SUM(waiting_for_delivery_stocks), 0) as incoming_units,
             SUM(CASE WHEN COALESCE(current_stocks, 0) <= 0 OR (three_days_average > 0 AND days_it_can_last < lead_time) THEN 1 ELSE 0 END) as low_stock_count,
             SUM(CASE WHEN po_needed > 0 THEN 1 ELSE 0 END) as reorder_needed_count'
        )->first();

        // PO tiles count only orders created in the window.
        $openPos = PurchasedOrder::where('workspace_id', $this->workspace->id)
            ->whereBetween('created_at', [$this->start->toDateTimeString(), $this->end->toDateTimeString()])
            ->whereNotIn('status', PurchasedOrder::CLOSED_STATUSES);

        // Shrinkage (bad + lost) over the selected window — a loss-monitoring tile.
        $shrinkage = DB::table('inventory_transactions')
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->selectRaw('COALESCE(SUM(rts_bad), 0) + COALESCE(SUM(lost), 0) as total')
            ->value('total');

        return [
            'active_skus' => (int) ($agg->active_skus ?? 0),
            'total_stock_on_hand' => (int) round($agg->total_stock_on_hand ?? 0),
            'low_stock_count' => (int) ($agg->low_stock_count ?? 0),
            'reorder_needed_count' => (int) ($agg->reorder_needed_count ?? 0),
            'incoming_units' => (int) round($agg->incoming_units ?? 0),
            'open_pos' => (int) (clone $openPos)->count(),
            'overdue_pos' => (int) (clone $openPos)
                ->whereNotNull('expected_delivery_date')
                ->whereDate('expected_delivery_date', '<', CarbonImmutable::now()->toDateString())
                ->count(),
            'shrinkage_units' => (int) $shrinkage,
        ];
    }

    /**
     * Daily inventory movement over the window: additive flows summed per day,
     * plus the end-of-day remaining-stock level.
     */
    public function movement(): array
    {
        $rows = DB::table('inventory_transactions')
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->groupByRaw('DATE(date)')
            ->selectRaw('DATE(date) as day')
            ->selectRaw('COALESCE(SUM(po_qty_in), 0) as po_qty_in')
            ->selectRaw('COALESCE(SUM(rts_goods_in), 0) as rts_goods_in')
            ->selectRaw('COALESCE(SUM(po_qty_out), 0) as po_qty_out')
            ->selectRaw('COALESCE(SUM(rts_goods_out), 0) as rts_goods_out')
            ->selectRaw('COALESCE(SUM(rts_bad), 0) as rts_bad')
            ->selectRaw('COALESCE(SUM(lost), 0) as lost')
            ->selectRaw('COALESCE(SUM(inventory_remaining_stock), 0) as remaining_stock')
            ->get()
            ->keyBy('day');

        return $this->overDays(fn (string $day) => [
            'po_qty_in' => (int) ($rows[$day]->po_qty_in ?? 0),
            'rts_goods_in' => (int) ($rows[$day]->rts_goods_in ?? 0),
            'po_qty_out' => (int) ($rows[$day]->po_qty_out ?? 0),
            'rts_goods_out' => (int) ($rows[$day]->rts_goods_out ?? 0),
            'rts_bad' => (int) ($rows[$day]->rts_bad ?? 0),
            'lost' => (int) ($rows[$day]->lost ?? 0),
            'remaining_stock' => (int) ($rows[$day]->remaining_stock ?? 0),
        ]);
    }

    /**
     * Movement reshaped into one array per series (categories + a flat list per
     * flow), so the movement endpoint returns chart-ready data with no client
     * reshaping.
     */
    public function movementSeries(): array
    {
        $movement = $this->movement();
        $keys = ['po_qty_in', 'rts_goods_in', 'po_qty_out', 'rts_goods_out', 'rts_bad', 'lost', 'remaining_stock'];
        $series = array_fill_keys($keys, []);

        foreach ($movement['values'] as $row) {
            foreach ($keys as $key) {
                $series[$key][] = $row[$key];
            }
        }

        return ['categories' => $movement['categories'], ...$series];
    }

    /** Count of purchase orders (issued in the window) in each of the eight statuses. */
    public function poStatusFunnel(): array
    {
        $counts = PurchasedOrder::where('workspace_id', $this->workspace->id)
            ->whereBetween('issue_date', [$this->start->toDateString(), $this->end->toDateString()])
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        return collect(PurchasedOrder::STATUSES)
            ->map(fn (string $label, int $status) => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Fulfilment split across open POs' line items: waiting / partial / delivered,
     * derived from ordered count vs. total delivered per line.
     */
    public function fulfillmentBreakdown(): array
    {
        $delivered = '(SELECT COALESCE(SUM(d.qty), 0) FROM inventory_purchased_order_item_deliveries d WHERE d.inventory_purchased_order_item_id = poi.id)';

        $row = DB::table('inventory_purchased_order_items as poi')
            ->join('inventory_purchased_orders as po', 'po.id', '=', 'poi.inventory_purchased_order_id')
            ->where('po.workspace_id', $this->workspace->id)
            ->whereBetween('po.issue_date', [$this->start->toDateString(), $this->end->toDateString()])
            ->whereNotIn('po.status', PurchasedOrder::CLOSED_STATUSES)
            ->selectRaw("SUM(CASE WHEN $delivered <= 0 THEN 1 ELSE 0 END) as waiting")
            ->selectRaw("SUM(CASE WHEN $delivered > 0 AND $delivered < poi.count THEN 1 ELSE 0 END) as partial")
            ->selectRaw("SUM(CASE WHEN $delivered >= poi.count AND $delivered > 0 THEN 1 ELSE 0 END) as delivered")
            ->first();

        return [
            'waiting' => (int) ($row->waiting ?? 0),
            'partial' => (int) ($row->partial ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
        ];
    }

    /** Daily shrinkage (bad + lost) over the window. */
    public function shrinkageTrend(): array
    {
        $rows = DB::table('inventory_transactions')
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->groupByRaw('DATE(date)')
            ->selectRaw('DATE(date) as day')
            ->selectRaw('COALESCE(SUM(rts_bad), 0) + COALESCE(SUM(lost), 0) as shrinkage')
            ->get()
            ->keyBy('day');

        return $this->overDays(fn (string $day) => (int) ($rows[$day]->shrinkage ?? 0));
    }

    /**
     * Reorder worklist: active items most in need of attention, worst PO-needed
     * first, then shortest days-of-cover.
     */
    public function stockHealth(int $limit = 12): array
    {
        return DB::query()
            ->fromSub($this->stockBase(true), 's')
            ->leftJoin('products', 'products.id', '=', 's.product_id')
            ->selectRaw('s.*, products.name as product_name')
            ->orderByDesc('po_needed')
            ->orderByRaw('CASE WHEN three_days_average > 0 THEN days_it_can_last ELSE 999999 END asc')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'sku' => $r->sku,
                'product_name' => $r->product_name,
                'current_stocks' => $r->current_stocks === null ? null : (int) round($r->current_stocks),
                'three_days_average' => (float) $r->three_days_average,
                'days_it_can_last' => (float) $r->days_it_can_last,
                'lead_time' => (int) $r->lead_time,
                'waiting_for_delivery_stocks' => $r->waiting_for_delivery_stocks === null ? null : (int) round($r->waiting_for_delivery_stocks),
                'po_needed' => (int) round($r->po_needed),
                'at_risk' => $r->three_days_average > 0 && $r->days_it_can_last < $r->lead_time,
            ])
            ->all();
    }

    /** Open POs whose expected delivery date falls in the window, soonest first. */
    public function upcomingDeliveries(int $limit = 8): array
    {
        $today = CarbonImmutable::now()->toDateString();

        return PurchasedOrder::where('workspace_id', $this->workspace->id)
            ->whereNotIn('status', PurchasedOrder::CLOSED_STATUSES)
            ->whereBetween('expected_delivery_date', [$this->start->toDateString(), $this->end->toDateString()])
            ->orderBy('expected_delivery_date')
            ->limit($limit)
            ->get()
            ->map(fn (PurchasedOrder $po) => [
                'id' => $po->id,
                'reference' => $po->cust_po_no ?: $po->control_no ?: $po->delivery_no ?: "PO #{$po->id}",
                'delivery_no' => $po->delivery_no,
                'expected_delivery_date' => optional($po->expected_delivery_date)->toDateString(),
                'status_label' => $po->status_label,
                'delayed' => $po->expected_delivery_date !== null
                    && $po->expected_delivery_date->toDateString() < $today,
            ])
            ->all();
    }

    /** Most recent physical-count adjustments recorded within the window. */
    public function recentAdjustments(int $limit = 8): array
    {
        return DB::table('inventory_item_discrepancies as d')
            ->join('inventory_items as i', 'i.id', '=', 'd.inventory_item_id')
            ->where('d.workspace_id', $this->workspace->id)
            ->whereBetween('d.date', [$this->start->toDateString(), $this->end->toDateString()])
            ->orderByDesc('d.date')
            ->orderByDesc('d.id')
            ->limit($limit)
            ->selectRaw('d.id, d.date, d.counted_qty, d.discrepancy, i.sku')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'date' => $r->date,
                'sku' => $r->sku,
                // Ledger stock the count was measured against = counted − discrepancy.
                'expected_qty' => (int) $r->counted_qty - (int) $r->discrepancy,
                'counted_qty' => (int) $r->counted_qty,
                'discrepancy' => (int) $r->discrepancy,
            ])
            ->all();
    }

    /** SKUs with the largest absolute count discrepancy recorded within the window. */
    public function topDiscrepancies(int $limit = 8): array
    {
        return DB::table('inventory_item_discrepancies as d')
            ->join('inventory_items as i', 'i.id', '=', 'd.inventory_item_id')
            ->where('d.workspace_id', $this->workspace->id)
            ->whereBetween('d.date', [$this->start->toDateString(), $this->end->toDateString()])
            ->where('d.discrepancy', '<>', 0)
            ->orderByRaw('ABS(d.discrepancy) DESC')
            ->selectRaw('i.sku, d.discrepancy')
            ->get()
            // One row per SKU — its largest-magnitude discrepancy in the window.
            ->unique('sku')
            ->take($limit)
            ->map(fn ($r) => [
                'sku' => $r->sku,
                'discrepancy' => (int) $r->discrepancy,
            ])
            ->values()
            ->all();
    }

    /**
     * Self-contained alerts endpoint: recomputes the figures it derives from so
     * it can be resolved independently of the other sections.
     */
    public function alertsFeed(): array
    {
        return $this->alerts($this->kpis(), $this->topDiscrepancies());
    }

    /**
     * Actionable alerts feed, derived from the same figures shown above so the
     * counts stay consistent with the tiles.
     */
    public function alerts(array $kpis, array $topDiscrepancies): array
    {
        $alerts = [];

        if ($kpis['low_stock_count'] > 0) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => 'critical',
                'title' => 'Low stock / stockout',
                'description' => "{$kpis['low_stock_count']} item(s) cannot cover their lead time.",
            ];
        }

        if ($kpis['reorder_needed_count'] > 0) {
            $alerts[] = [
                'type' => 'reorder',
                'severity' => 'warning',
                'title' => 'Reorder required',
                'description' => "{$kpis['reorder_needed_count']} item(s) need a purchase order.",
            ];
        }

        if ($kpis['overdue_pos'] > 0) {
            $alerts[] = [
                'type' => 'overdue_po',
                'severity' => 'warning',
                'title' => 'Overdue purchase orders',
                'description' => "{$kpis['overdue_pos']} open PO(s) are past their expected delivery date.",
            ];
        }

        // "Large" = an absolute discrepancy of 10 units or more on any SKU.
        $large = collect($topDiscrepancies)->filter(fn ($d) => abs($d['discrepancy']) >= 10);
        if ($large->isNotEmpty()) {
            $alerts[] = [
                'type' => 'discrepancy',
                'severity' => 'info',
                'title' => 'Large inventory discrepancies',
                'description' => "{$large->count()} SKU(s) have a count variance of 10+ units.",
            ];
        }

        return $alerts;
    }

    /**
     * Map a callback over each ISO day in the window, returning the categories
     * and the per-day values so every chart shares one gap-filled axis.
     *
     * @return array{categories: list<string>, values: list<mixed>}
     */
    private function overDays(callable $valueFor): array
    {
        $categories = [];
        $values = [];

        foreach (CarbonPeriod::create($this->start, '1 day', $this->end) as $date) {
            $day = $date->toDateString();
            $categories[] = $day;
            $values[] = $valueFor($day);
        }

        return ['categories' => $categories, 'values' => $values];
    }
}
