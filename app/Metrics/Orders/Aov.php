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
        return RollupReader::ratioBreakdown('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::ratioPerPage('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::ratioPerShop('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::ratioPerUser('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
    }
}
