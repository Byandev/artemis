<?php

namespace App\Metrics\Orders;

use App\Metrics\Concerns\HasMetricSource;

final class TotalOrders
{
    use HasMetricSource;

    public function compute(int $workspaceId, array $date_range, array $filter): int
    {
        $reader = $this->reader();

        return (int) $reader::sum('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $reader = $this->reader();

        return $reader::breakdown('confirmed_count', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perPage('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perShop('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        $reader = $this->reader();

        return $reader::perUser('confirmed_count', $workspaceId, $date_range, $filter);
    }
}
