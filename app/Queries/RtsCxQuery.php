<?php

namespace App\Queries;

use Illuminate\Support\Collection;

class RtsCxQuery extends RtsBaseQuery
{
    private const BUCKETS = ['no_report', '0-10', '11-20', '21-30', '31-40', '41-50', '51-60', '61-70', '71-80', '81-90', '91-100'];

    private string $reportType = 'latest';

    public function ofType(string $type): static
    {
        $this->reportType = in_array($type, ['initial', 'latest']) ? $type : 'latest';

        return $this;
    }

    public function get(): Collection
    {
        $rateExpr = 'pnr.order_fail * 100.0 / NULLIF(pnr.order_fail + pnr.order_success, 0)';

        $bucketExpr = "
            CASE
                WHEN pnr.order_id IS NULL
                  OR (pnr.order_fail = 0 AND pnr.order_success = 0)
                    THEN 'no_report'
                WHEN ROUND({$rateExpr}, 2) <= 10  THEN '0-10'
                WHEN ROUND({$rateExpr}, 2) <= 20  THEN '11-20'
                WHEN ROUND({$rateExpr}, 2) <= 30  THEN '21-30'
                WHEN ROUND({$rateExpr}, 2) <= 40  THEN '31-40'
                WHEN ROUND({$rateExpr}, 2) <= 50  THEN '41-50'
                WHEN ROUND({$rateExpr}, 2) <= 60  THEN '51-60'
                WHEN ROUND({$rateExpr}, 2) <= 70  THEN '61-70'
                WHEN ROUND({$rateExpr}, 2) <= 80  THEN '71-80'
                WHEN ROUND({$rateExpr}, 2) <= 90  THEN '81-90'
                ELSE '91-100'
            END
        ";

        $bucketList = implode(', ', array_map(fn ($b) => "'{$b}'", self::BUCKETS));

        return $this->query
            ->selectRaw("({$bucketExpr}) AS cx_rts_bucket, ".self::METRICS_SQL)
            ->leftJoin('pancake_order_phone_number_reports AS pnr', function ($join) {
                $join->on('pnr.order_id', '=', 'pancake_orders.id')
                    ->where('pnr.type', '=', $this->reportType);
            })
            ->groupByRaw("({$bucketExpr})")
            ->havingRaw(self::HAVING_SQL)
            ->orderByRaw("FIELD(cx_rts_bucket, {$bucketList})")
            ->get();
    }
}
