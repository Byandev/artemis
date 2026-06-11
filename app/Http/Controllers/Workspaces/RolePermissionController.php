<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolePermissionController extends Controller
{
    use AuthorizesRequests;

    public function edit(Request $request, Workspace $workspace, Role $role)
    {
        abort_unless(
            $request->user()->hasPermission(PermissionEnum::ViewRoles, $workspace)
                || $request->user()->hasPermission(PermissionEnum::ManageRolePermissions, $workspace),
            403
        );

        $disabled = $workspace->disabledPermissionCategories();
        $hiddenNames = $workspace->hiddenPermissionNames();

        $permissions = Permission::orderBy('category')->orderBy('name')
            ->when($disabled, fn ($q) => $q->whereNotIn('category', $disabled))
            ->when($hiddenNames, fn ($q) => $q->whereNotIn('name', $hiddenNames))
            ->get();

        $grouped = $permissions->groupBy('category')->map(function ($items, $category) use ($role) {
            return [
                'category' => $category,
                'permissions' => $items->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'granted' => $role->permissions->contains($p->id),
                ]),
            ];
        })->values();

        return Inertia::render('roles/permissions', [
            'workspace' => $workspace,
            'role' => $role->load('permissions'),
            'groups' => $grouped,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Role $role)
    {
        $this->authorize(PermissionEnum::ManageRolePermissions->value, $workspace);

        $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $disabled = $workspace->disabledPermissionCategories();
        $hiddenNames = $workspace->hiddenPermissionNames();

        // Preserve any already-granted permissions the form doesn't show — those
        // in a disabled category, or individually hidden (e.g. dashboard module
        // off) — so they aren't wiped on sync.
        $preservedIds = ($disabled || $hiddenNames)
            ? $role->permissions()
                ->where(fn ($q) => $q
                    ->whereIn('category', $disabled ?: ['__none__'])
                    ->orWhereIn('name', $hiddenNames ?: ['__none__']))
                ->pluck('permissions.id')->all()
            : [];

        $role->permissions()->sync(array_values(array_unique(
            array_merge($request->input('permission_ids', []), $preservedIds)
        )));

        return back()->with('success', 'Permissions updated.');
    }
}
