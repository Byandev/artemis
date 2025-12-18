<?php

namespace App\Http\Controllers\Workspaces\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $scalingProductCount = Product::where('workspace_id', $workspace->id)
            ->where('status', 'Scaling')
            ->count();

        $testingProductCount = Product::where('workspace_id', $workspace->id)
            ->where('status', 'Testing')
            ->count();

        $inactiveProductCount = Product::where('workspace_id', $workspace->id)
            ->where('status', 'Inactive')
            ->count();

        $totalProductCount = Product::where('workspace_id', $workspace->id)
            ->count();

        return Inertia::render('workspaces/products/analytics', [
            'workspace' => $workspace,
            'summary' => [
                'scaling_product_count' => $scalingProductCount,
                'testing_product_count' => $testingProductCount,
                'inactive_product_count' => $inactiveProductCount,
                'total_product_count' => $totalProductCount,
            ],
        ]);
    }

    public function topAdvertisingSales(Workspace $workspace, Request $request)
    {
        return Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectSub(function ($q) {
                $q->from('ad_records')
                    ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                    ->join('pages', 'pages.id', '=', 'ads.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
            }, 'advertising_sales')
            ->orderByDesc(function ($q) {
                $q->from('ad_records')
                    ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                    ->join('pages', 'pages.id', '=', 'ads.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
            })
            ->limit(10)
            ->get();
    }

    public function topSales(Workspace $workspace, Request $request)
    {
        return Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectSub(function ($q) {
                $q->from('orders')
                    ->join('pages', 'pages.id', '=', 'orders.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(orders.total_amount), 0)');
            }, 'sales')
            ->orderByDesc(function ($q) {
                $q->from('orders')
                    ->join('pages', 'pages.id', '=', 'orders.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(orders.total_amount), 0)');
            })
            ->limit(10)
            ->get();
    }

    public function topAdSpent(Workspace $workspace, Request $request)
    {
        return Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectSub(function ($q) {
                $q->from('ad_records')
                    ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                    ->join('pages', 'pages.id', '=', 'ads.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(ad_records.spend), 0)');
            }, 'ad_spent')
            ->orderByDesc(function ($q) {
                $q->from('ad_records')
                    ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                    ->join('pages', 'pages.id', '=', 'ads.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(ad_records.spend), 0)');
            })
            ->limit(10)
            ->get();
    }

    public function topRoas(Workspace $workspace, Request $request)
    {
        return Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectRaw("
            CASE
                WHEN (
                    SELECT COALESCE(SUM(ar.spend), 0)
                    FROM ad_records ar
                    JOIN ads a ON a.id = ar.ad_id
                    JOIN pages p ON p.id = a.page_id
                    WHERE p.product_id = products.id
                ) = 0
                THEN 0
                ELSE (
                    (
                        SELECT COALESCE(SUM(o.total_amount), 0)
                        FROM orders o
                        JOIN pages p2 ON p2.id = o.page_id
                        WHERE p2.product_id = products.id
                    ) / (
                        SELECT COALESCE(SUM(ar2.spend), 0)
                        FROM ad_records ar2
                        JOIN ads a2 ON a2.id = ar2.ad_id
                        JOIN pages p3 ON p3.id = a2.page_id
                        WHERE p3.product_id = products.id
                    )
                )
            END AS roas
        ")

            ->orderByDesc('roas')
            ->limit(10)
            ->get();
    }

    public function topRts(Workspace $workspace, Request $request)
    {
        return Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectSub(function ($q) {
                $q->selectRaw("
                COALESCE(
                    (
                        (SELECT COUNT(*)
                         FROM orders o
                         JOIN pages p2 ON p2.id = o.page_id
                         WHERE p2.product_id = products.id
                           AND o.confirmed_at IS NOT NULL
                           AND o.returning_at IS NOT NULL)
                        /
                        NULLIF(
                            (
                                (SELECT COUNT(*)
                                 FROM orders o2
                                 JOIN pages p3 ON p3.id = o2.page_id
                                 WHERE p3.product_id = products.id
                                   AND o2.confirmed_at IS NOT NULL
                                   AND o2.returning_at IS NOT NULL)
                                +
                                (SELECT COUNT(*)
                                 FROM orders o3
                                 JOIN pages p4 ON p4.id = o3.page_id
                                 WHERE p4.product_id = products.id
                                   AND o3.confirmed_at IS NOT NULL
                                   AND o3.delivered_at IS NOT NULL)
                            ),
                            0
                        )
                    ),
                    0
                )
            ");
            }, 'rts')
            ->orderBy('rts', 'asc')
            ->limit(10)
            ->get();
    }
}
