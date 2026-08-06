<?php

namespace Modules\Inventory\Support;

/**
 * The correlated-subquery SQL fragments for an item's computed stock columns, all
 * keyed off `inventory_items.id`.
 *
 * Shared by the flat list query, the parent/child roll-up, and the daily snapshot
 * command so all three compute stock identically — a snapshot that disagreed with
 * the list it is a photo of would be worse than no snapshot at all.
 *
 * Every fragment is delegated to InventoryStockColumns rather than restated here.
 * The two used to carry their own copies of the same SQL, which meant a change to
 * one silently made the items list and the dashboard disagree; this class is now
 * only the alias map the list and the snapshot share.
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
        return [
            'current_stocks' => InventoryStockColumns::currentStocks(),
            'discrepancy' => InventoryStockColumns::latestDiscrepancy(),
            'discrepancy_counted_qty' => InventoryStockColumns::latestCounted(),
            'discrepancy_date' => InventoryStockColumns::latestDiscrepancyDate(),
            // Everything still owed on open orders — the full committed figure
            // the reorder maths works from, so a raised PO is never ordered
            // twice. requested_stocks breaks out the part of it that no supplier
            // has been given yet, which is a flow signal rather than a quantity.
            'waiting_for_delivery_stocks' => InventoryStockColumns::waitingStocks(),
            'requested_stocks' => InventoryStockColumns::requestedStocks(),
            'remaining_after_fulfillment' => InventoryStockColumns::remainingAfterFulfillment(),
            'stocks_needed_for_lead_time' => InventoryStockColumns::stocksNeededForLeadTime(),
            'po_qty' => InventoryStockColumns::coverageBuffer(),
            'po_needed' => InventoryStockColumns::poNeeded(),
            'days_it_can_last' => InventoryStockColumns::daysItCanLast(),
        ];
    }
}
