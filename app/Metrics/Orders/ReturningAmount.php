<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class ReturningAmount
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return round(RollupReader::sum('entered_returning_amount', $workspaceId, $date_range, $filter), 2);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::breakdown('entered_returning_amount', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perPage('entered_returning_amount', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perShop('entered_returning_amount', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perUser('entered_returning_amount', $workspaceId, $date_range, $filter);
    }
}
