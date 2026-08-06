<?php

namespace Modules\Inventory\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\PurchasedOrder;

/**
 * Single source of truth for the SQL that turns the raw inventory ledger into
 * the derived stock figures shown across the module (current stock, waiting for
 * delivery, days of cover, PO needed, …).
 *
 * These fragments read from three derived tables that applyJoins() attaches, so
 * **every caller must call applyJoins() on its query before selecting any of
 * them**. Selecting a fragment without the join is a SQL error, which is the
 * intended failure — a silently wrong number would be worse.
 *
 * Why joins and not correlated subqueries: the fragments compose (po_needed
 * contains remaining_after_fulfillment, which contains current_stocks), so as
 * subqueries the innermost lookups were re-evaluated once per level. A page
 * selecting the full column set emitted 24 subqueries per row for 3 real
 * lookups. Joining each lookup once cut a sorted page from 19.2ms to 4.9ms and
 * a full pass from 19.1ms to 5.1ms, with byte-identical output across every
 * item and column.
 *
 * The derived tables are not workspace-filtered — they aggregate per item id
 * and the join key does the scoping. That is fine while the ledger is modest;
 * if inventory_transactions grows large enough that building the latest-row
 * table dominates, push the workspace predicate down into these subqueries.
 */
final class InventoryStockColumns
{
    /** Latest transaction row per item. */
    private const TX = 'inv_tx';

    /** Latest physical-count adjustment per item. */
    private const DISC = 'inv_disc';

    /** Outstanding purchase-order quantities per item, split by release state. */
    private const PO = 'inv_po';

    /**
     * Attach the three derived tables the fragments below read from. Call once
     * per query, on a query selecting from (or joined to) `inventory_items`.
     *
     * Each derived table is grouped by `inventory_item_id`, so it contributes at
     * most one row per item and cannot fan the outer query out — counts, sums
     * and team-scoping `whereHas` clauses all behave exactly as before.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyJoins($query): void
    {
        $query
            ->leftJoinSub(self::latestTransactionQuery(), self::TX, self::TX.'.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoinSub(self::latestDiscrepancyQuery(), self::DISC, self::DISC.'.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoinSub(self::purchaseOrderQuery(), self::PO, self::PO.'.inventory_item_id', '=', 'inventory_items.id');
    }

    /** Raw ledger stock = the latest transaction's running remaining_qty. */
    public static function rawCurrentStocks(): string
    {
        return self::TX.'.remaining_qty';
    }

    /** Signed offset from the latest physical count, layered onto the ledger. */
    public static function latestDiscrepancy(): string
    {
        return self::DISC.'.discrepancy';
    }

    /** The counted quantity of that same latest adjustment. */
    public static function latestCounted(): string
    {
        return self::DISC.'.counted_qty';
    }

    /** The date of that same latest adjustment. */
    public static function latestDiscrepancyDate(): string
    {
        return self::DISC.'.date';
    }

    /**
     * Displayed stock = ledger stock + latest physical-count offset. NULL only
     * when the item has neither a transaction nor a count, so the cell reads
     * "—" rather than collapsing to 0.
     */
    public static function currentStocks(): string
    {
        $raw = self::rawCurrentStocks();
        $disc = self::latestDiscrepancy();

        return "(CASE WHEN $raw IS NULL AND $disc IS NULL THEN NULL ELSE COALESCE($raw, 0) + COALESCE($disc, 0) END)";
    }

    /**
     * Quantity still owed on every open order, whatever stage it sits at. The
     * whole open book; for the reorder maths use releasedStocks().
     */
    public static function waitingStocks(): string
    {
        $released = self::releasedStocks();
        $requested = self::requestedStocks();

        // NULLIF keeps "nothing outstanding" rendering as "—" not 0, matching
        // the two halves it is made of.
        return "NULLIF(COALESCE($released, 0) + COALESCE($requested, 0), 0)";
    }

    /**
     * Quantity owed on orders a supplier is actually working on — the only
     * incoming figure the reorder maths trusts. See
     * PurchasedOrder::RELEASED_STATUSES.
     */
    public static function releasedStocks(): string
    {
        return self::PO.'.released';
    }

    /**
     * Quantity owed on orders still waiting for approval or payment. Shown as
     * its own column so nothing is hidden — it is simply not counted as stock.
     */
    public static function requestedStocks(): string
    {
        return self::PO.'.requested';
    }

    /**
     * Stock left once incoming deliveries land and unfulfilled orders are met.
     *
     * Counts every open order, released or not. A purchase order that exists is
     * already committed quantity: excluding it here would push po_needed to
     * reorder stock that has in fact been ordered, and the second PO is a
     * genuine double-order — a worse failure than the one it would be guarding
     * against.
     *
     * The risk that an order sits unapproved for weeks is real, but it is a
     * flow problem, not a quantity problem. It is surfaced by requestedStocks()
     * as its own column and by the PO-flow dashboard, which ages each order by
     * stage — not by inflating the reorder figure.
     */
    public static function remainingAfterFulfillment(): string
    {
        $current = self::currentStocks();
        $waiting = self::waitingStocks();

        return "(COALESCE($current, 0) + COALESCE($waiting, 0) - COALESCE(inventory_items.unfulfilled_count, 0))";
    }

    /** The safety buffer alone: days_of_coverage extra days of expected demand. */
    public static function coverageBuffer(): string
    {
        return '(COALESCE(inventory_items.days_of_coverage, 0) * COALESCE(inventory_items.three_days_average, 0))';
    }

    /** Expected demand across the lead-time window. */
    public static function stocksNeededForLeadTime(): string
    {
        return '(COALESCE(inventory_items.lead_time, 0) * COALESCE(inventory_items.three_days_average, 0))';
    }

    /**
     * How much to purchase to cover the safety buffer plus lead-time demand,
     * given what's on hand once released deliveries land.
     *
     * Incoming stock is subtracted exactly once, via remainingAfterFulfillment
     * (which already adds releasedStocks in). Subtracting it again on top would
     * double-count and understate the reorder.
     */
    public static function poNeeded(): string
    {
        $remaining = self::remainingAfterFulfillment();

        return 'GREATEST(0, '.self::coverageBuffer().' + '.self::stocksNeededForLeadTime()." - $remaining)";
    }

    /** Days of cover: runway at the current 3-day average burn rate. */
    public static function daysItCanLast(): string
    {
        $remaining = self::remainingAfterFulfillment();

        return "(CASE WHEN inventory_items.three_days_average > 0 THEN $remaining / inventory_items.three_days_average ELSE 0 END)";
    }

    /**
     * The latest ledger row per item. ROW_NUMBER rather than MAX(date): several
     * transactions can share a date, and the list has always taken the highest
     * id among them as the current one.
     *
     * The window function is the one thing here the query builder cannot
     * express, so the ranking column is the only raw expression — the rest is
     * an ordinary builder query.
     */
    private static function latestTransactionQuery(): QueryBuilder
    {
        $ranked = DB::table('inventory_transactions')
            ->select('inventory_item_id', 'remaining_qty')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY inventory_item_id ORDER BY date DESC, id DESC) rn');

        return DB::query()->fromSub($ranked, 'x')
            ->select('inventory_item_id', 'remaining_qty')
            ->where('x.rn', 1);
    }

    /** The latest physical count per item, tie-broken the same way. */
    private static function latestDiscrepancyQuery(): QueryBuilder
    {
        $ranked = DB::table('inventory_item_discrepancies')
            ->select('inventory_item_id', 'discrepancy', 'counted_qty', 'date')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY inventory_item_id ORDER BY date DESC, id DESC) rn');

        return DB::query()->fromSub($ranked, 'x')
            ->select('inventory_item_id', 'discrepancy', 'counted_qty', 'date')
            ->where('x.rn', 1);
    }

    /**
     * Undelivered quantity per item, split by whether a supplier has the order.
     * Deliveries are pre-aggregated once for the whole table rather than looked
     * up per line, and NULLIF keeps an item with nothing outstanding reading as
     * "—" rather than 0.
     *
     * The two conditional sums need CASE WHEN, which the builder has no fluent
     * form for; the status lists go in as bindings rather than interpolated.
     */
    private static function purchaseOrderQuery(): QueryBuilder
    {
        $deliveries = DB::table('inventory_purchased_order_item_deliveries')
            ->select('inventory_purchased_order_item_id')
            ->selectRaw('SUM(qty) qty')
            ->groupBy('inventory_purchased_order_item_id');

        $owed = 'GREATEST(0, poi.count - COALESCE(dl.qty, 0))';
        $sumWhen = fn (array $statuses) => 'NULLIF(SUM(CASE WHEN po.status IN ('
            .implode(',', array_fill(0, count($statuses), '?'))
            .") THEN $owed ELSE 0 END), 0)";

        return DB::table('inventory_purchased_order_items as poi')
            ->join('inventory_purchased_orders as po', 'po.id', '=', 'poi.inventory_purchased_order_id')
            ->leftJoinSub($deliveries, 'dl', 'dl.inventory_purchased_order_item_id', '=', 'poi.id')
            ->whereIn('po.status', PurchasedOrder::AWAITING_DELIVERY_STATUSES)
            ->groupBy('poi.inventory_item_id')
            ->select('poi.inventory_item_id')
            ->selectRaw($sumWhen(PurchasedOrder::RELEASED_STATUSES).' released', PurchasedOrder::RELEASED_STATUSES)
            ->selectRaw($sumWhen(PurchasedOrder::REQUESTED_STATUSES).' requested', PurchasedOrder::REQUESTED_STATUSES);
    }
}
