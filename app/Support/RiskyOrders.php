<?php

namespace App\Support;

/**
 * What makes a confirmed order risky.
 *
 * Two reasons qualify, and they cannot overlap: the customer's number had no
 * report at all — nothing was known about them — or it had one showing at
 * least MIN_ORDERS past orders with a return rate at or above RTS_THRESHOLD.
 * Everything else is a customer with either a record of taking delivery or too
 * short a history to read as a risk.
 *
 * These are the CSR dashboard's, which counts one CSR's own risky orders off
 * pancake_orders. The CSR analytics card counts the workspace's off the nightly
 * page_order_report_breakdown_daily_records rollup and holds its own copy of
 * both numbers (CSRController::RISKY_RTS_THRESHOLD / RISKY_MIN_ORDERS), as does
 * the frontend card's footnote, which prints them. All three must agree —
 * change one, change the others, or the two pages will quietly disagree about
 * which orders were risky.
 */
final class RiskyOrders
{
    /** At or above this customer return rate, an order is risky. */
    public const RTS_THRESHOLD = 0.40;

    /**
     * Below this many past orders, the rate is not taken as evidence of risk.
     *
     * One order back out of one is a 100% return rate on a customer nobody has
     * seen twice. The floor is what separates a customer with a habit from a
     * customer with an accident, and without it the rate alone swept in every
     * near-new number that had a single bad delivery.
     */
    public const MIN_ORDERS = 6;
}
