<?php

namespace App\Http\Controllers\Workspaces;

use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Workspace $workspace)
    {
        $roles = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->orderBy('deleted_at', 'asc')
            ->get();

        return Inertia::render('roles/index', [
            'workspace' => $workspace,
            'roles' => $roles,
        ]);
    }
    public function create(Workspace $workspace)
    {
        return Inertia::render('roles/create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'role')->where('workspace_id', $workspace->id),
            ],
            'description' => 'nullable|string',
        ]);

        $workspace->roles()->create($validated);
        return back()->with('success', 'Role created successfully!');
    }


    // Edit Role Definition
    public function edit(Workspace $workspace, Role $role)
    {
        return Inertia::render('roles/edit', [
            'workspace' => $workspace,
            'role' => $role
        ]);
    }

    public function update(Request $request, Workspace $workspace, Role $role)
    {
        $validated = $request->validate([
            'role' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $role->update($validated);

        return redirect()->route('roles.index', $workspace->slug);
    }

    public function archive(Workspace $workspace, Role $role)
    {
        $role->delete();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
            'archived' => 'true'
        ])->with('success', 'Role archived successfully!');
    }

    public function restore(Workspace $workspace, $role)
    {
        $role = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($role);

        $role->restore();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug
        ])->with('success', 'Role restored successfully!');
    }

}
