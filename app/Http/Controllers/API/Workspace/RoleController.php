<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The roles page's list, fetched after the page renders so the shell doesn't
 * wait on it. Mutations stay on Workspaces\RoleController; archived roles
 * have their own page there.
 */
class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewRoles->value, $workspace);

        return QueryBuilder::for(Role::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'description', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate(min(max($request->integer('per_page', 10), 1), 100))
            ->withQueryString();
    }
}
