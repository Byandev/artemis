<?php

namespace App\Http\Controllers\API\Workspace;

use App\Models\Page;
use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    public function index(Workspace $workspace)
    {
        return QueryBuilder::for(Page::class)
            ->where('workspace_id', $workspace->id)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
