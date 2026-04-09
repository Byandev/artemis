<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $roles = QueryBuilder::for(Role::withTrashed()->where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'description', 'created_at', 'deleted_at'])
            ->defaultSort('-created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('roles/index', [
            'workspace' => $workspace,
            'roles' => $roles,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $role->update($validated);

        return redirect()->route('roles.index', $workspace->slug);
    }

    public function destroy(Workspace $workspace, Role $role)
    {
        $role->delete();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
            'archived' => 'true',
        ])->with('success', 'Role archived successfully!');
    }

    public function restore(Workspace $workspace, $role)
    {
        $role = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($role);

        $role->restore();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
        ])->with('success', 'Role restored successfully!');
    }
}
