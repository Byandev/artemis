<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Products\Models\Product;
use Spatie\QueryBuilder\QueryBuilder;

class AnalyticsController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        abort_unless($workspace->products_module_enabled, 404);

        $user = $request->user();
        $scoped = fn () => Product::where('workspace_id', $workspace->id)
            ->when(
                TeamVisibility::shouldScope($user, $workspace),
                fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($user, $workspace)),
            );

        $scalingProductCount = $scoped()->where('status', 'Scaling')->count();
        $testingProductCount = $scoped()->where('status', 'Testing')->count();
        $inactiveProductCount = $scoped()->where('status', 'Inactive')->count();
        $totalProductCount = $scoped()->count();

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
        abort_unless($workspace->products_module_enabled, 404);

        $allowedMetrics = ['advertising_sales', 'ad_spent', 'sales', 'roas', 'rts'];
        $requestedMetrics = array_filter(explode(',', $request->input('metric', '')));

        $query = Product::ofWorkspace($workspace)
            ->when(
                TeamVisibility::shouldScope($request->user(), $workspace),
                fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($request->user(), $workspace)),
            )
            ->select('products.*');

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
