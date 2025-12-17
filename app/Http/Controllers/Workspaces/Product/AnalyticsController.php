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

    public function topSales(Workspace $workspace, Request $request)
    {
        $products = Product::where('workspace_id', $workspace->id)
            ->select('products.*')
            ->selectSub(function ($q) {
                $q->from('ad_records')
                    ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                    ->join('pages', 'pages.id', '=', 'ads.page_id')
                    ->whereColumn('pages.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
            }, 'sales')
            ->limit(10)
            ->get();
    }
}
