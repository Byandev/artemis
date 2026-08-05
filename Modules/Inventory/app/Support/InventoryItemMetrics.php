<?php

namespace Modules\Inventory\Support;

use Modules\Inventory\Models\PurchasedOrder;

/**
 * The correlated-subquery SQL fragments for an item's computed stock columns, all
 * keyed off `inventory_items.id`.
 *
 * Shared by the flat list query, the parent/child roll-up, and the daily snapshot
 * command so all three compute stock identically — a snapshot that disagreed with
 * the list it is a photo of would be worse than no snapshot at all.
 */
class InventoryItemMetrics
{
    /**
     * Every computed column, keyed by the alias the list and the snapshot both use.
     *
     * @return array<string, string>
     */
    public static function sql(): array
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
}
