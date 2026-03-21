<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace,
        ]);
    }

    // Grouped endpoints reuse the same filter logic below
    public function groupByPages(Request $request, Workspace $workspace)
    {
        $query = Order::selectRaw('
            pages.id AS id,
            pages.name AS name,
            SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->leftJoin('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->groupBy('pancake_orders.page_id', 'pages.name', 'pages.id')
            ->orderBy('total_orders', 'DESC');

        $filterOptions = (clone $query)
            ->get(['pages.id', 'pages.name']);

        // If 'all' parameter is present, return all data without pagination (for graph)
        if ($request->has('all')) {
            $filtered = $query->get();

            return response()->json([
                'data' => $filtered,
                'filter_options' => $filterOptions,
            ]);
        }

        // Otherwise, return paginated data (for table view)
        $filtered = $query->paginate($request->input('per_page', 15));

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
    }

    public function groupByShops(Request $request, Workspace $workspace)
    {
        $query = Order::selectRaw('
            shops.id AS id,
            shops.name AS name,
            SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->leftJoin('shops', 'shops.id', '=', 'pancake_orders.shop_id')
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->groupBy('pancake_orders.shop_id', 'shops.name', 'shops.id')
            ->orderBy('total_orders', 'DESC');

        $filterOptions = (clone $query)
            ->get(['shops.id', 'shops.name']);

        // If 'all' parameter is present, return all data without pagination (for graph)
        if ($request->has('all')) {
            $filtered = $query->get();

            return response()->json([
                'data' => $filtered,
                'filter_options' => $filterOptions,
            ]);
        }

        // Otherwise, return paginated data (for table view)
        $filtered = $query->paginate($request->input('per_page', 15));

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
    }

    public function groupByUsers(Request $request, Workspace $workspace)
    {
        $query = Order::selectRaw('
            users.id AS id,
            users.name AS name,
            SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->leftJoin('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->leftJoin('users', 'users.id', '=', 'pages.owner_id')
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->groupBy('pages.owner_id', 'users.name', 'users.id')
            ->orderBy('total_orders', 'DESC');

        $filterOptions = (clone $query)
            ->get(['users.id', 'users.name']);

        // If 'all' parameter is present, return all data without pagination (for graph)
        if ($request->has('all')) {
            $filtered = $query->get();

            return response()->json([
                'data' => $filtered,
                'filter_options' => $filterOptions,
            ]);
        }

        // Otherwise, return paginated data (for table view)
        $filtered = $query->paginate($request->input('per_page', 15));

        return response()->json(array_merge(
            $filtered->toArray(),
            ['filter_options' => $filterOptions]
        ));
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        $query = Order::selectRaw('
            shipping_addresses.district_name AS city_name,
            shipping_addresses.province_name AS province_name,
                SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id')
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->groupBy('shipping_addresses.district_name', 'shipping_addresses.province_name')
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0') // Only include cities with orders
            ->orderBy('total_orders', 'DESC');

        // If 'all' parameter is present, return all data without pagination (for heatmap)
        if ($request->has('all')) {
            $grouped = $query->get();

            return response()->json(['data' => $grouped]);
        }

        // Otherwise, return paginated data (for table view)
        $grouped = $query->paginate($request->input('per_page', 15));

        return response()->json($grouped);
    }
}
