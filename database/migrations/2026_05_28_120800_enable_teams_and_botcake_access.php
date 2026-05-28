<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workspaces')->update([
            'teams_module_enabled' => true,
            'botcake_module_enabled' => true,
            'updated_at' => now(),
        ]);

        $permissions = [
            'View Teams' => 'Teams',
            'View Botcake Sequences' => 'Botcake',
            'View Botcake Flows' => 'Botcake',
        ];

        foreach ($permissions as $name => $category) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'category' => $category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($permissions))
            ->pluck('id');

        if ($permissionIds->count() !== count($permissions)) {
            return;
        }

        $roleIds = DB::table('roles')
            ->leftJoin('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->leftJoin('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where(function ($query) {
                $query
                    ->whereIn('roles.name', ['admin', 'owner'])
                    ->orWhereIn('permissions.name', [
                        'View Members',
                        'View Roles',
                        'View Pages',
                        'View Shops',
                    ]);
            })
            ->distinct()
            ->pluck('roles.id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep access decisions made after this migration intact.
    }
};
