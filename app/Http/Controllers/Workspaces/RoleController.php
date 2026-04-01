<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Workspace $workspace)
    {
        $roles = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->orderBy('deleted_at', 'asc')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'deleted_at' => $role->deleted_at,
                    'status' => $role->trashed() ? 'Archived' : 'Active',
                ];
            });

        return Inertia::render('roles/index', [
            'workspace' => $workspace,
            'roles' => $roles,
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

    // public function update(Request $request, Workspace $workspace, Role $role)
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //     ]);

    //     $role->update($validated);

    //     return redirect()->route('roles.index', $workspace->slug);
    // }

    public function update(Request $request, Workspace $workspace, Role $role)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('workspace_id', $workspace->id)->ignore($role->id),
            ],
            'description' => 'nullable|string',
        ]);

        $role->update($validated);

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug
        ])->with('success', 'Role updated successfully!');
    }

    public function archive(Workspace $workspace, Role $role)
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
