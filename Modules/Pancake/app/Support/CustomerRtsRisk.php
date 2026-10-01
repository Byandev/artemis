<?php

namespace Modules\Pancake\Support;

/**
 * How likely the customer behind an order is to send it back.
 *
 * The rate is the customer's own return history on their phone number — the
 * same expression CxRtsRateSort, RiskScoreSort and the CSR verification card
 * rank on, so an order banded here reads at the same rate everywhere else.
 *
 * Both boundaries are numbers the app already uses rather than new ones:
 * LOW_MAX is the green band in the RTS pages' rtsColor(), and HIGH_MIN is the
 * threshold at which CSRController tells a CSR to ring before shipping.
 */
class CustomerRtsRisk
{
    /** The phone number has no report behind it — nothing is known either way. */
    public const NO_REPORT = 'no_report';

    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    /** At or below this return rate the customer sits in the RTS pages' green band. */
    private const LOW_MAX = 0.15;

    /** At or above this, an order is worth a verification call before it ships. */
    private const HIGH_MIN = 0.55;

    /**
     * The customer's return rate for an order, as a fraction, or NULL when the
     * number has no report.
     *
     * `latest` is the report as it stands now, which is what a list of orders
     * being worked today should be judged on — the `initial` row beside it is
     * the number as it looked when the order first came in.
     *
     * @param  string  $orderIdColumn  the outer query's order id, e.g. `pancake_orders.id`
     */
    public static function rateSql(string $orderIdColumn = 'pancake_orders.id'): string
    {
        return "(
            SELECT SUM(r.order_fail) / NULLIF(SUM(r.order_fail) + SUM(r.order_success), 0)
            FROM pancake_order_phone_number_reports r
            WHERE r.order_id = {$orderIdColumn} AND r.type = 'latest'
        )";
    }

    /**
     * The band a rate falls in.
     *
     * No report is its own answer rather than a low one: an unknown customer is
     * exactly the case the verification call exists for.
     */
    public static function level(?float $rate): string
    {
        return match (true) {
            $rate === null => self::NO_REPORT,
            $rate <= self::LOW_MAX => self::LOW,
            $rate < self::HIGH_MIN => self::MEDIUM,
            default => self::HIGH,
        };
    }
}
