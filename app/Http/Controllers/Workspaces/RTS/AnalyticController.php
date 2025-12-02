<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelJourneyNotification;
use App\Models\Workspace;
use Inertia\Inertia;

class AnalyticController extends Controller
{
    public function index(Workspace $workspace)
    {
        $rtsStats = Order::selectRaw('
            ROUND(
                (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage,
            SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            SUM(CASE WHEN status IN (4,5) THEN total_amount ELSE 0 END) AS returned_amount,
            SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS delivered_count
        ')
            ->where('workspace_id', $workspace->id)
            ->first();

        $tracked_orders = Order::where('workspace_id', $workspace->id)
            ->has('parcelJourneyNotifications')
            ->count();

        $sent_parcel_journey_notifications = ParcelJourneyNotification::whereHas('order', function ($query) use ($workspace) {
            $query->where('workspace_id', $workspace->id);
        })->count();

        // Grouped by Page (include page name)
        $groupedRtsStatsByPage = Order::selectRaw('
            orders.page_id,
            pages.name as page_name,
            ROUND(
                (SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage,
            SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            SUM(CASE WHEN orders.status IN (4,5) THEN orders.total_amount ELSE 0 END) AS returned_amount,
            SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count
        ')
            ->leftJoin('pages', 'pages.id', '=', 'orders.page_id')
            ->where('orders.workspace_id', $workspace->id)
            ->groupBy('orders.page_id', 'pages.name')
            ->get();

        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace,
            'data' => [
                'rts_rate_percentage' => $rtsStats->rts_rate_percentage,
                'returned_count' => $rtsStats->returned_count,
                'delivered_count' => $rtsStats->delivered_count,
                'returned_amount' => $rtsStats->returned_amount,
                'tracked_orders' => $tracked_orders,
                'sent_parcel_journey_notifications' => $sent_parcel_journey_notifications,
                'grouped_rts_stats_by_page' => $groupedRtsStatsByPage,
            ],
        ]);
    }
}
