<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelJourneyNotification;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticController extends Controller
{
    public function index(Workspace $workspace)
    {
        // Compute summary widgets on the index so the page can pre-render
        // essential KPI cards while still supporting the detailed endpoints.
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
            ->ofWorkspace($workspace)
            ->first();

        $tracked_orders = Order::ofWorkspace($workspace)
            ->has('parcelJourneyNotifications')
            ->count();

        $sent_parcel_journey_notifications = ParcelJourneyNotification::whereHas('order', function ($query) use ($workspace) {
            $query->ofWorkspace($workspace);
        })->count();

        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace,
            'data' => [
                'rts_rate_percentage' => optional($rtsStats)->rts_rate_percentage ?? 0,
                'returned_count' => optional($rtsStats)->returned_count ?? 0,
                'delivered_count' => optional($rtsStats)->delivered_count ?? 0,
                'returned_amount' => optional($rtsStats)->returned_amount ?? 0,
                'tracked_orders' => $tracked_orders,
                'sent_parcel_journey_notifications' => $sent_parcel_journey_notifications,
            ],
        ]);
    }

    // API endpoints for grouped stats
    public function groupByPage(Request $request, Workspace $workspace)
    {
        $groupedQuery = Order::selectRaw('
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
            ->ofWorkspace($workspace);

        if ($request->filled('page_ids')) {
            $ids = (array) $request->input('page_ids');
            $groupedQuery->whereIn('orders.page_id', $ids);
        }

        if ($request->filled('date')) {
            $groupedQuery->whereDate('orders.confirmed_at', $request->input('date'));
        }

        $grouped = $groupedQuery->groupBy('orders.page_id', 'pages.name', 'pages.id')->get();

        return response()->json($grouped);
    }

    public function groupByShops(Request $request, Workspace $workspace)
    {
        $groupedQuery = Order::selectRaw('
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
            ->ofWorkspace($workspace);

        if ($request->filled('shop_ids')) {
            $ids = (array) $request->input('shop_ids');
            $groupedQuery->whereIn('orders.shop_id', $ids);
        }

        if ($request->filled('date')) {
            $groupedQuery->whereDate('orders.confirmed_at', $request->input('date'));
        }

        $grouped = $groupedQuery->groupBy('orders.shop_id', 'shops.name', 'shops.id')->get();

        return response()->json($grouped);
    }

    public function groupByUsers(Request $request, Workspace $workspace)
    {
        $groupedQuery = Order::selectRaw('
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
            ->ofWorkspace($workspace);

        if ($request->filled('user_ids')) {
            $ids = (array) $request->input('user_ids');
            $groupedQuery->whereIn('pages.owner_id', $ids);
        }

        if ($request->filled('date')) {
            $groupedQuery->whereDate('orders.confirmed_at', $request->input('date'));
        }

        $grouped = $groupedQuery->groupBy('pages.owner_id', 'users.name', 'users.id')->get();

        return response()->json($grouped);
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        $groupedQuery = Order::selectRaw('
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
            ->ofWorkspace($workspace);

        if ($request->filled('city_ids')) {
            $ids = (array) $request->input('city_ids');
            $groupedQuery->whereIn('shipping_addresses.id', $ids);
        }

        if ($request->filled('date')) {
            $groupedQuery->whereDate('orders.confirmed_at', $request->input('date'));
        }

        $grouped = $groupedQuery->groupBy('shipping_addresses.district_name', 'shipping_addresses.id')->get();

        return response()->json($grouped);
    }
}
