<?php

namespace App\Http\Controllers\API\Workspace;

use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Workspace $workspace)
    {
        return QueryBuilder::for(User::class)
            ->whereHas('workspaces', function ($query) use ($workspace) {
                $query->where('workspaces.id', $workspace->id);
            })
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
