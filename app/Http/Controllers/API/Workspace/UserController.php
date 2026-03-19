<?php

namespace App\Http\Controllers\API\Workspace;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use App\Models\WorkspaceUser;

class UserController extends Controller
{
    public function index(Workspace $workspace)
    {
        return QueryBuilder::for(User::class)
            ->whereHas('workspaces', function ($query) use ($workspace) {
                $query->where('workspaces.id', $workspace->id)
                    ->withTrashed();
            })
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }


}
