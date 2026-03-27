<?php

namespace App\Queries;

use Illuminate\Support\Collection;

class RtsPriceQuery extends RtsBaseQuery
{
    private const BUCKETS = ['0-250', '251-500', '501-750', '751-1000', '1001-1500', '1501-2000', '2001-3000', '3001-5000', '5000+'];

    private const PRICE_EXPR = "
        CASE
            WHEN pancake_orders.final_amount IS NULL  THEN NULL
            WHEN pancake_orders.final_amount <= 250   THEN '0-250'
            WHEN pancake_orders.final_amount <= 500   THEN '251-500'
            WHEN pancake_orders.final_amount <= 750   THEN '501-750'
            WHEN pancake_orders.final_amount <= 1000  THEN '751-1000'
            WHEN pancake_orders.final_amount <= 1500  THEN '1001-1500'
            WHEN pancake_orders.final_amount <= 2000  THEN '1501-2000'
            WHEN pancake_orders.final_amount <= 3000  THEN '2001-3000'
            WHEN pancake_orders.final_amount <= 5000  THEN '3001-5000'
            ELSE '5000+'
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
        $bucketList = implode(', ', array_map(fn ($b) => "'{$b}'", self::BUCKETS));

        return $this->query
            ->selectRaw('(' . self::PRICE_EXPR . ') AS price_bucket, ' . self::METRICS_SQL)
            ->groupByRaw('(' . self::PRICE_EXPR . ')')
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0')
            ->orderByRaw("FIELD(price_bucket, {$bucketList})")
            ->get();
    }
}
