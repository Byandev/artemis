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

    public function edit(Workspace $workspace, Role $role)
    {
        $this->authorize(PermissionEnum::ManageRolePermissions->value, $workspace);

        $disabled = $this->disabledCategoriesFor($workspace);

        $permissions = Permission::orderBy('category')->orderBy('name')
            ->when($disabled, fn ($q) => $q->whereNotIn('category', $disabled))
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

        $disabled = $this->disabledCategoriesFor($workspace);

        // Preserve any already-granted permissions that belong to disabled categories
        // (the form doesn't show them, so they'd otherwise be wiped on sync).
        $preservedIds = $disabled
            ? $role->permissions()->whereIn('category', $disabled)->pluck('permissions.id')->all()
            : [];

        $role->permissions()->sync(array_values(array_unique(
            array_merge($request->input('permission_ids', []), $preservedIds)
        )));

        return back()->with('success', 'Permissions updated.');
    }

    /**
     * @return array<int, string>
     */
    private function disabledCategoriesFor(Workspace $workspace): array
    {
        return array_values(array_filter([
            $workspace->finance_module_enabled ? null : 'Finance',
            $workspace->inventory_module_enabled ? null : 'Inventory',
            $workspace->products_module_enabled ? null : 'Products',
            $workspace->teams_module_enabled ? null : 'Teams',
            $workspace->checklist_module_enabled ? null : 'Checklist',
            $workspace->csr_module_enabled ? null : 'CSR',
        ]));
    }
}
