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
        $pageIds = request()->query('page_ids', []);

        // --- Base RTS STATS ---
        $rtsStats = Order::selectRaw('
        ROUND(
            (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0), 2
        ) AS rts_rate_percentage,
        SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        SUM(CASE WHEN status IN (4,5) THEN total_amount ELSE 0 END) AS returned_amount,
        SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS delivered_count
    ')
            ->where('workspace_id', $workspace->id)
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('page_id', $pageIds))
            ->first();

        // --- Tracked Orders ---
        $tracked_orders = Order::where('workspace_id', $workspace->id)
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('page_id', $pageIds))
            ->has('parcelJourneyNotifications')
            ->count();

        // --- Sent Notifications ---
        $sent_parcel_journey_notifications = ParcelJourneyNotification::whereHas('order', function ($query) use ($workspace, $pageIds) {
            $query->where('workspace_id', $workspace->id)
                ->when(! empty($pageIds), fn ($q) => $q->whereIn('page_id', $pageIds));
        })->count();

        // --- Grouped by Page ---
        $groupedRtsStatsByPage = Order::selectRaw('
        orders.page_id AS id,
        pages.name AS page_name,
        SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ')
            ->leftJoin('pages', 'pages.id', '=', 'orders.page_id')
            ->where('orders.workspace_id', $workspace->id)
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('orders.page_id', $pageIds))
            ->groupBy('orders.page_id', 'pages.name')
            ->get();

        // --- Grouped by Users ---
        $groupedRtsStatsByUsers = Order::selectRaw('
        pages.owner_id AS id,
        users.name AS user_name,
        SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ')
            ->leftJoin('pages', 'pages.id', '=', 'orders.page_id')
            ->leftJoin('users', 'users.id', '=', 'pages.owner_id')
            ->where('orders.workspace_id', $workspace->id)
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('orders.page_id', $pageIds))
            ->groupBy('pages.owner_id', 'users.name')
            ->get();

        // --- Grouped by Cities ---
        $groupedRtsStatsByCities = Order::selectRaw('
        shipping_addresses.id AS id,
        shipping_addresses.district_name AS city_name,
        SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
        ')
            ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'orders.id')
            ->where('orders.workspace_id', $workspace->id)
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('orders.page_id', $pageIds))
            ->groupBy('shipping_addresses.id', 'shipping_addresses.district_name')
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
                'grouped_rts_stats_by_users' => $groupedRtsStatsByUsers,
                'grouped_rts_stats_by_cities' => $groupedRtsStatsByCities,
            ],
            'page_ids' => $pageIds,
        ]);
    }
}
