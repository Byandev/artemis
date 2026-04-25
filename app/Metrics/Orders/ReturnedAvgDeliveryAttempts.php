<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class ReturnedAvgDeliveryAttempts
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return RollupReader::divide('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::divideBreakdown('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerPage('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerShop('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerUser('sum_delivery_attempts_returned', 'count_delivery_attempts_returned', $workspaceId, $date_range, $filter);
    }
}
