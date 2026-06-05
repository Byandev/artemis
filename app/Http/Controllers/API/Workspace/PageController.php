<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Workspace;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $allowedOwners = TeamScope::allowedOwnerIds($request->user(), $workspace);

        return QueryBuilder::for(Page::class)
            ->where('workspace_id', $workspace->id)
            ->when($allowedOwners !== null, fn ($q) => $q->whereIn('owner_id', $allowedOwners))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
