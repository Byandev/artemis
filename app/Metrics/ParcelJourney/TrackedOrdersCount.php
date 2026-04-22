<?php

namespace App\Metrics\ParcelJourney;

use App\Support\AnalyticsRollup\ReadsRollup;

final class TrackedOrdersCount
{
    use ReadsRollup;

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return (float) $this->rollupBaseQuery($workspaceId, $date_range, $filter)->sum('tracked_orders_count');
    }
}
