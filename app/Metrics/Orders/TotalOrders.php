<?php

namespace App\Metrics\Orders;

use App\Metrics\Orders\Concerns\OrdersMetricBase;

final class TotalOrders
{
    use OrdersMetricBase;

    public function compute(int $workspaceId): int
    {
        return (int) $this->confirmedBase($workspaceId)->count();
    }
}
