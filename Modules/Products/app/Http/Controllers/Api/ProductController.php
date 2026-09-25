<?php

namespace Modules\Products\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Modules\Products\Models\Product;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index(Workspace $workspace)
    {
        abort_unless($workspace->products_module_enabled, 404);

        return QueryBuilder::for(
            Product::where('workspace_id', $workspace->id)
                ->when(
                    TeamVisibility::shouldScope(auth()->user(), $workspace),
                    fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo(auth()->user(), $workspace)),
                )
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
