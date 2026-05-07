<?php

namespace App\Support\Analytics;

use App\Models\WorkspacePageDailyMetric;

class AllCustomerConversionRate
{
    public static function live(WorkspacePageDailyMetric $metric): float
    {
        return self::compute(
            (int) $metric->confirmed_count,
            (int) $metric->all_customer_count,
        );
    }

    public static function rollup(iterable $metrics): float
    {
        $orders = 0;
        $customers = 0;

        foreach ($metrics as $metric) {
            $orders += (int) $metric->confirmed_count;
            $customers += (int) $metric->all_customer_count;
        }

        return self::compute($orders, $customers);
    }

    public static function compute(int $orders, int $customers): float
    {
        if ($customers === 0) {
            return 0.0;
        }

        return round($orders / $customers, 4);
    }
}
