<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Workspace;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    public function index(Workspace $workspace)
    {
        return QueryBuilder::for(
            Page::where('workspace_id', $workspace->id)
                ->visibleTo(auth()->user(), $workspace)
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
