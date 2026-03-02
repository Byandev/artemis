<?php

namespace App\Metrics\Orders;

use App\Metrics\Orders\Concerns\OrdersMetricBase;

final class Aov
{
    use OrdersMetricBase;

    public function compute(int $workspaceId): float
    {
        $row = $this->confirmedBase($workspaceId)
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*),0), 0) as aov')
            ->first();

        return (float) ($row->aov ?? 0);
    }
}
