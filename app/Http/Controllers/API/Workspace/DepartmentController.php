<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The departments page's list, fetched after the page renders so the shell
 * doesn't wait on it. Mutations stay on Workspaces\DepartmentController.
 */
class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewDepartments->value, $workspace);

        return QueryBuilder::for(
            Department::ofWorkspace($workspace)
                ->withCount('users')
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'code', 'is_active', 'created_at', 'users_count'])
            ->defaultSort('-created_at')
            ->paginate(min(max($request->integer('per_page', 10), 1), 100))
            ->withQueryString();
    }
}
