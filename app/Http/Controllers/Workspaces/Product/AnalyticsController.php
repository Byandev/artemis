<?php

namespace App\Http\Controllers\Workspaces\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

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

    public function metrics(Workspace $workspace, Request $request)
    {
        $allowedMetrics = ['advertising_sales', 'ad_spent', 'sales', 'roas', 'rts'];
        $requestedMetrics = array_filter(explode(',', $request->input('metric', '')));

        $query = Product::ofWorkspace($workspace)->select('products.*');

        // Apply each requested metric scope
        foreach ($requestedMetrics as $metric) {
            $query->when(
                in_array($metric, $allowedMetrics),
                fn ($q) => $q->{'with'.str($metric)->studly()}(request('start_date'), request('end_date'))
            );
        }

        return QueryBuilder::for($query)
            ->allowedSorts([
                'name',
                'code',
                'status',
                'advertising_sales',
                'ad_spent',
                'sales',
                'roas',
                'rts',
            ])
            ->paginate($request->input('per_page', 10));
    }
}
