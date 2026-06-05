<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $allowedOwners = TeamScope::allowedOwnerIds($request->user(), $workspace);

        return QueryBuilder::for(User::class)
            ->whereHas('workspaces', function ($query) use ($workspace) {
                $query->where('workspaces.id', $workspace->id);
            })
            ->when($allowedOwners !== null, fn ($q) => $q->whereIn('users.id', $allowedOwners))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
