<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class Aov
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return RollupReader::divide('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::divideBreakdown('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerPage('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerShop('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerUser('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }
}
