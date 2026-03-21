<?php

namespace App\Metrics\ParcelJourney;

use App\Models\ParcelJourneyNotificationLog;
use Modules\Pancake\Models\Order;

final class TrackedOrdersCount
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $saved = ParcelJourneyNotificationLog::whereHas('page', function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        })
            ->whereBetween('date', [$date_range['start_date'], $date_range['end_date']])
            ->sum('tracked_orders');

        $saved = $saved ?? 0;

        $new = Order::where('workspace_id', $workspaceId)
            ->whereHas('parcelJourneys', function ($query) use ($date_range) {
                $query->whereHas('notifications', function ($query) use ($date_range) {
                    $query->where('status', 'sent')
                        ->whereBetween('created_at', [$date_range['start_date'], $date_range['end_date']]);
                });
            })
            ->count();

        return $saved + $new;
    }
}
