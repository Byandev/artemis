<?php

namespace App\Queries;

use Illuminate\Support\Collection;

class RtsDeliveryAttemptsQuery extends RtsBaseQuery
{
    private const ATTEMPT_EXPR = "
        CASE
            WHEN pancake_orders.delivery_attempts IS NULL THEN NULL
            WHEN pancake_orders.delivery_attempts >= 5   THEN '5+'
            ELSE CAST(pancake_orders.delivery_attempts AS CHAR)
        END
    ";

    private const METRICS_SQL = '
        SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ';

    public function get(): Collection
    {
        return $this->query
            ->selectRaw('(' . self::ATTEMPT_EXPR . ') AS delivery_attempts, ' . self::METRICS_SQL)
            ->groupByRaw('(' . self::ATTEMPT_EXPR . ')')
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0')
            ->orderByRaw('ISNULL(MIN(pancake_orders.delivery_attempts)), MIN(pancake_orders.delivery_attempts) ASC')
            ->get();
    }
}
