<?php

namespace App\Http\Controllers\API\Workspace;

use App\Models\Product;
use App\Models\Team;
use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index(Workspace $workspace)
    {
        return QueryBuilder::for(Product::class)
            ->where('workspace_id', $workspace->id)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
