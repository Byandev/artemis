<?php

namespace App\Metrics\Orders;

use App\Metrics\Concerns\HasMetricSource;

final class ReturnedAmount
{
    use HasMetricSource;

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $reader = $this->reader();

        return round($reader::sum('returned_amount', $workspaceId, $date_range, $filter), 2);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $reader = $this->reader();

        return $reader::breakdown('returned_amount', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perPage('returned_amount', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perShop('returned_amount', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perUser('returned_amount', $workspaceId, $date_range, $filter);
    }
}
