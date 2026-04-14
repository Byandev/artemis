<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolePermissionController extends Controller
{
    public function edit(Workspace $workspace, Role $role)
    {
        $permissions = Permission::orderBy('category')->orderBy('name')->get();

        $grouped = $permissions->groupBy('category')->map(function ($items, $category) use ($role) {
            return [
                'category' => $category,
                'permissions' => $items->map(fn ($p) => [
                    'id'      => $p->id,
                    'name'    => $p->name,
                    'granted' => $role->permissions->contains($p->id),
                ]),
            ];
        })->values();

        return Inertia::render('roles/permissions', [
            'workspace'   => $workspace,
            'role'        => $role->load('permissions'),
            'groups'      => $grouped,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Role $role)
    {
        $request->validate([
            'permission_ids'   => 'present|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role->permissions()->sync($request->permission_ids);

        return back()->with('success', 'Permissions updated.');
    }
}
