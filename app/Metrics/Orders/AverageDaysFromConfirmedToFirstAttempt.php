<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;

final class AverageDaysFromConfirmedToFirstAttempt
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return RollupReader::divide('sum_days_confirmed_to_first_attempt', 'count_confirmed_to_first_attempt', $workspaceId, $date_range, $filter);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        return RollupReader::divideBreakdown('sum_days_confirmed_to_first_attempt', 'count_confirmed_to_first_attempt', $workspaceId, $date_range, $filter, $group);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerPage('sum_days_confirmed_to_first_attempt', 'count_confirmed_to_first_attempt', $workspaceId, $date_range, $filter);
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerShop('sum_days_confirmed_to_first_attempt', 'count_confirmed_to_first_attempt', $workspaceId, $date_range, $filter);
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return RollupReader::dividePerUser('sum_days_confirmed_to_first_attempt', 'count_confirmed_to_first_attempt', $workspaceId, $date_range, $filter);
    }
}
