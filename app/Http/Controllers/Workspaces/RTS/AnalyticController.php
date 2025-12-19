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
    public function index(Request $request, Workspace $workspace)
    {
        // Apply filters to summary stats
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
            ->applyRtsFilters($request)
            ->first();

        // Count tracked orders (also filtered)
        $tracked_orders = Order::ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->has('parcelJourneyNotifications')
            ->count();

        // Count sent PJN (also filtered)
        $sent_parcel_journey_notifications = ParcelJourneyNotification::whereHas('order', function ($query) use ($workspace, $request) {
            $query->ofWorkspace($workspace)->applyRtsFilters($request);
        })->count();

        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace,
            'filters' => [
                'page_ids' => array_map('intval', (array) $request->input('page_ids', [])),
                'shop_ids' => array_map('intval', (array) $request->input('shop_ids', [])),
                'user_ids' => array_map('intval', (array) $request->input('user_ids', [])),
                'start_date' => $request->input('start_date', null),
                'end_date' => $request->input('end_date', null),
            ],
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

    // Grouped endpoints reuse the same filter logic below
    public function groupByPages(Request $request, Workspace $workspace)
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

        $filtered = (clone $groupedQuery)
            ->applyRtsFilters($request)
            ->groupBy('orders.page_id', 'pages.name', 'pages.id')
            ->orderBy('total_orders', 'DESC')
            ->paginate($request->input('per_page', 15));

        $filterOptions = (clone $groupedQuery)
            ->groupBy('orders.page_id', 'pages.name', 'pages.id')
            ->get(['pages.id', 'pages.name']);

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
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

        $filtered = (clone $groupedQuery)
            ->applyRtsFilters($request)
            ->groupBy('orders.shop_id', 'shops.name', 'shops.id')
            ->orderBy('total_orders', 'DESC')
            ->paginate($request->input('per_page', 15));

        $filterOptions = (clone $groupedQuery)
            ->groupBy('orders.shop_id', 'shops.name', 'shops.id')
            ->get(['shops.id', 'shops.name']);

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
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

        $filtered = (clone $groupedQuery)
            ->applyRtsFilters($request)
            ->groupBy('pages.owner_id', 'users.name', 'users.id')
            ->orderBy('total_orders', 'DESC')
            ->paginate($request->input('per_page', 15));

        $filterOptions = (clone $groupedQuery)
            ->groupBy('pages.owner_id', 'users.name', 'users.id')
            ->get(['users.id', 'users.name']);

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        $grouped = Order::selectRaw('
            shipping_addresses.district_name AS city_name,
            shipping_addresses.province_name AS province_name,
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
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->groupBy('shipping_addresses.district_name', 'shipping_addresses.province_name')
            ->havingRaw('SUM(CASE WHEN orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0') // Only include cities with orders
            ->orderBy('total_orders', 'DESC')
            ->paginate($request->input('per_page', 15));

        return response()->json($grouped);
    }
}
