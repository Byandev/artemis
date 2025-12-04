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

        // Grouped by Page
        $groupedRtsStatsByPage = Order::selectRaw('
            pages.id AS id,
            pages.name AS name,
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
            ->groupBy('orders.page_id', 'pages.name', 'pages.id')
            ->get();

        // Grouped by Shops
        $groupedRtsStatsByShops = Order::selectRaw('
            shops.id AS id,
            shops.name AS name,
            SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->leftJoin('shops', 'shops.id', '=', 'orders.shop_id')
            ->where('orders.workspace_id', $workspace->id)
            ->groupBy('orders.shop_id', 'shops.name', 'shops.id')
            ->get();

        // Grouped by Users
        $groupedRtsStatsByUsers = Order::selectRaw('
            users.id AS id,
            users.name AS name,
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
            ->groupBy('pages.owner_id', 'users.name', 'users.id')
            ->get();

        // Grouped by Cities
        $groupedRtsStatsByCities = Order::selectRaw('
            shipping_addresses.id AS id,
            shipping_addresses.district_name AS name,
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
            ->groupBy('shipping_addresses.district_name', 'shipping_addresses.id')
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
                'grouped_rts_stats_by_shops' => $groupedRtsStatsByShops,
                'grouped_rts_stats_by_users' => $groupedRtsStatsByUsers,
                'grouped_rts_stats_by_cities' => $groupedRtsStatsByCities,
            ],
        ]);
    }
}
