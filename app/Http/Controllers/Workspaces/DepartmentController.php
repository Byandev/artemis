<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreDepartmentRequest;
use App\Http\Requests\Workspaces\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewDepartments->value, $workspace);

        $departments = QueryBuilder::for(
            Department::ofWorkspace($workspace)
                ->withCount('users')
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'code', 'is_active', 'created_at', 'users_count'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/departments/index', [
            'workspace' => $workspace,
            'departments' => $departments,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(StoreDepartmentRequest $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreateDepartments->value, $workspace);

        $workspace->departments()->create($request->validated());

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, Workspace $workspace, Department $department)
    {
        $this->authorize(Permission::EditDepartments->value, $workspace);
        $this->ensureBelongsToWorkspace($department, $workspace);

        $department->update($request->validated());

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, Department $department)
    {
        $this->authorize(Permission::DeleteDepartments->value, $workspace);
        $this->ensureBelongsToWorkspace($department, $workspace);

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }

    /**
     * Guard against ID-bound bindings resolving a department from another workspace.
     */
    private function ensureBelongsToWorkspace(Department $department, Workspace $workspace): void
    {
        abort_if(
            $department->workspace_id !== $workspace->id,
            403,
            'This department does not belong to the current workspace.'
        );
    }
}
