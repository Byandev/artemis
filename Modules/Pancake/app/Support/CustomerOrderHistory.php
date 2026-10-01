<?php

namespace Modules\Pancake\Support;

/**
 * How many orders the customer behind an order has placed in total.
 *
 * Read off `pancake_phone_number_reports` — Pancake's cumulative report for a
 * phone number — rather than counted from our own `pancake_orders`: the report
 * spans every page and shop Pancake knows about, so a returning customer counts
 * their whole history there and only however much we happen to have synced here.
 *
 * That table is also the one the sync keeps current for every number, while the
 * per-order rows beside it are only refreshed when that order is itself synced.
 * So a customer who ordered again yesterday has the new total here today.
 */
class CustomerOrderHistory
{
    /**
     * The customer's lifetime order count for an order, or NULL when their
     * number has no report behind it.
     *
     * Failed and successful orders added together: a delivered order and a
     * returned one are both orders the customer placed, and the Customer RTS
     * rate beside this is the ratio between the same two numbers. `warning` is
     * left out — it is a flag on the number, not a count of orders.
     *
     * Reached through `pancake_order_phone_number_reports` because that is where
     * an order's phone number is already stored in the same spelling the report
     * is keyed on (see SyncPhoneNumberReportsAction: last ten digits behind a
     * leading zero). `shipping_addresses` keeps whatever Pancake was given —
     * +63, no prefix, spaces — which will not match.
     *
     * `latest` alone, matching CustomerRtsRisk::rateSql(): the `initial` row
     * beside it names the same phone number, and joining both would count every
     * customer's history twice.
     *
     * @param  string  $orderIdColumn  the outer query's order id, e.g. `pancake_orders.id`
     */
    public static function totalSql(string $orderIdColumn = 'pancake_orders.id'): string
    {
        return "(
            SELECT SUM(p.order_fail + p.order_success)
            FROM pancake_order_phone_number_reports r
            JOIN pancake_phone_number_reports p ON p.phone_number = r.phone_number
            WHERE r.order_id = {$orderIdColumn} AND r.type = 'latest'
        )";
    }
}
