<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use App\Queries\RtsLocationQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace->loadMissing([
                'shops' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pages' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
            ]),
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

    public function locationAnalytics(Request $request, Workspace $workspace)
    {
        return Inertia::render('workspaces/rts/location-analytics', [
            'workspace' => $workspace->loadMissing([
                'shops' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name'),
                'pages' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name'),
            ]),
        ]);
    }

    public function groupByDeliveryAttempts(Request $request, Workspace $workspace)
    {
        $rows = Order::selectRaw('
            CASE
                WHEN pancake_orders.delivery_attempts >= 5 THEN \'5+\'
                WHEN pancake_orders.delivery_attempts IS NULL THEN NULL
                ELSE CAST(pancake_orders.delivery_attempts AS CHAR)
            END AS delivery_attempts,
            SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
            SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
            ROUND(
                (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->ofWorkspace($workspace)
            ->applyRtsFilters($request)
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0')
            ->groupByRaw('
                CASE
                    WHEN pancake_orders.delivery_attempts >= 5 THEN \'5+\'
                    WHEN pancake_orders.delivery_attempts IS NULL THEN NULL
                    ELSE CAST(pancake_orders.delivery_attempts AS CHAR)
                END
            ')
            ->orderByRaw('ISNULL(MIN(pancake_orders.delivery_attempts)), MIN(pancake_orders.delivery_attempts) ASC')
            ->get();

        return response()->json($rows);
    }

    public function groupByProvinces(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsLocationQuery($workspace, $request))
                ->byProvince()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10))
        );
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsLocationQuery($workspace, $request))
                ->byCity()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10))
        );
    }
}
