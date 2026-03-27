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

    public function get(): Collection
    {
        return $this->query
            ->selectRaw('(' . self::ATTEMPT_EXPR . ') AS delivery_attempts, ' . self::METRICS_SQL)
            ->groupByRaw('(' . self::ATTEMPT_EXPR . ')')
            ->havingRaw(self::HAVING_SQL)
            ->orderByRaw('ISNULL(MIN(pancake_orders.delivery_attempts)), MIN(pancake_orders.delivery_attempts) ASC')
            ->get();
    }
}
