<?php

namespace App\Queries;

use Illuminate\Support\Collection;

class RtsOrderFrequencyQuery extends RtsBaseQuery
{
    private const FREQ_EXPR = "
        CASE
            WHEN (pancake_orders.customer_succeed_order_count + pancake_orders.customer_returned_order_count) >= 5
                THEN '5+'
            ELSE CAST((pancake_orders.customer_succeed_order_count + pancake_orders.customer_returned_order_count) AS CHAR)
        END
    ";

    public function get(): Collection
    {
        return $this->query
            ->selectRaw('(' . self::FREQ_EXPR . ') AS order_frequency, ' . self::METRICS_SQL)
            ->whereRaw('(pancake_orders.customer_succeed_order_count + pancake_orders.customer_returned_order_count) > 0')
            ->groupByRaw('(' . self::FREQ_EXPR . ')')
            ->havingRaw(self::HAVING_SQL)
            ->orderByRaw('MIN(pancake_orders.customer_succeed_order_count + pancake_orders.customer_returned_order_count) ASC')
            ->get();
    }
}
