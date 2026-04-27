<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class TotalOrders
{
    public function compute(int $workspaceId, array $date_range, array $filter): int
    {
        return (int) RollupReader::sum('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::breakdown('confirmed_count', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perPage('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perShop('confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perUser('confirmed_count', $workspaceId, $date_range, $filter);
    }
}
