<?php

namespace App\Metrics\Orders;

use App\Metrics\Concerns\HasMetricSource;

final class ReturnedAvgDeliveryAttempts
{
    use HasMetricSource;

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $reader = $this->reader();

        return $reader::divide('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $reader = $this->reader();

        return $reader::divideBreakdown('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::dividePerPage('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::dividePerShop('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::dividePerUser('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }
}
