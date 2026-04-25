<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class DeliveredAmount
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return round(RollupReader::sum('delivered_amount', $workspaceId, $date_range, $filter), 2);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::breakdown('delivered_amount', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perPage('delivered_amount', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perShop('delivered_amount', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::perUser('delivered_amount', $workspaceId, $date_range, $filter);
    }
}
