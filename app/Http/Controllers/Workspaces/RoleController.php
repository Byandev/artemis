<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewRoles->value, $workspace);

        // The list itself loads from the browser API so the page renders
        // without waiting on the query — see API\Workspace\RoleController.
        return Inertia::render('roles/index', [
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function archived(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewRoles->value, $workspace);

        $roles = QueryBuilder::for(Role::onlyTrashed()->where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'description', 'deleted_at'])
            ->defaultSort('-deleted_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('roles/archived', [
            'workspace' => $workspace,
            'roles' => $roles,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreateRoles->value, $workspace);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('workspace_id', $workspace->id),
            ],
            'description' => 'nullable|string',
        ]);

        $workspace->roles()->create($validated);

        return back()->with('success', 'Role created successfully!');
    }

    public function update(Request $request, Workspace $workspace, Role $role)
    {
        $this->authorize(Permission::EditRoles->value, $workspace);
        abort_unless($role->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $role->update($validated);

        return redirect()->route('roles.index', $workspace->slug);
    }

    public function destroy(Workspace $workspace, Role $role)
    {
        $this->authorize(Permission::DeleteRoles->value, $workspace);
        abort_unless($role->workspace_id === $workspace->id, 404);

        $role->delete();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
        ])->with('success', 'Role archived successfully!');
    }

    public function restore(Workspace $workspace, $role)
    {
        $this->authorize(Permission::DeleteRoles->value, $workspace);

        $role = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($role);

        $role->restore();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
        ])->with('success', 'Role restored successfully!');
    }
}
