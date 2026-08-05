<?php

namespace Modules\Inventory\Support;

/**
 * Single source of truth for the correlated-subquery SQL that turns the raw
 * inventory ledger into the derived stock figures shown across the module
 * (current stock, waiting-for-delivery, days-of-cover, PO-needed, …).
 *
 * The Inventory Items list (InventoryItemController) builds on these fragments,
 * so every view of the numbers agrees. Every fragment is written against the
 * `inventory_items` table alias, so callers must select from `inventory_items`
 * (or join it).
 */
final class InventoryStockColumns
{
    /** Raw ledger stock = the latest transaction's running remaining_qty. */
    public static function rawCurrentStocks(): string
    {
        return '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
    }

    /** Signed offset from the latest physical count, layered onto the ledger. */
    public static function latestDiscrepancy(): string
    {
        return '(SELECT discrepancy FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
    }

    /** The counted quantity of that same latest adjustment. */
    public static function latestCounted(): string
    {
        return '(SELECT counted_qty FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
    }

    /** The date of that same latest adjustment. */
    public static function latestDiscrepancyDate(): string
    {
        return '(SELECT date FROM inventory_item_discrepancies WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
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
     * Quantity still OWED on orders awaiting delivery (open POs, status not in
     * 7/8): the undelivered remainder per item, not the full ordered count.
     * NULLIF keeps items with nothing outstanding showing as "—" not 0.
     */
    public static function waitingStocks(): string
    {
        return '(SELECT NULLIF(SUM(GREATEST(0, poi.count - COALESCE((SELECT SUM(d.qty) FROM inventory_purchased_order_item_deliveries d WHERE d.inventory_purchased_order_item_id = poi.id), 0))), 0) FROM inventory_purchased_order_items poi WHERE poi.inventory_item_id = inventory_items.id AND EXISTS (SELECT 1 FROM inventory_purchased_orders po WHERE poi.inventory_purchased_order_id = po.id AND po.status not in (7,8)))';
    }

    /** Stock left once incoming deliveries land and unfulfilled orders are met. */
    public static function remainingAfterFulfillment(): string
    {
        $current = self::currentStocks();
        $waiting = self::waitingStocks();

        return "(COALESCE($current, 0) + COALESCE($waiting, 0) - COALESCE(inventory_items.unfulfilled_count, 0))";
    }

    /** How much to purchase to cover lead-time demand, given what's incoming. */
    public static function poNeeded(): string
    {
        $waiting = self::waitingStocks();
        $remaining = self::remainingAfterFulfillment();

        return "GREATEST(0, (COALESCE(inventory_items.lead_time, 0) * COALESCE(inventory_items.three_days_average, 0)) - COALESCE($waiting, 0) - $remaining)";
    }

    /** Days of cover: runway at the current 3-day average burn rate. */
    public static function daysItCanLast(): string
    {
        $remaining = self::remainingAfterFulfillment();

        return "(CASE WHEN inventory_items.three_days_average > 0 THEN $remaining / inventory_items.three_days_average ELSE 0 END)";
    }
}
