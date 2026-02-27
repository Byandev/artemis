<?php

namespace App\Metrics\Orders;

use App\Metrics\Orders\Concerns\OrdersMetricBase;

final class TotalSales
{
    use OrdersMetricBase;

    public function compute(int $workspaceId): float
    {
        return (float) $this
            ->confirmedBase($workspaceId)
            ->sum('pancake_orders.final_amount');
    }
}
