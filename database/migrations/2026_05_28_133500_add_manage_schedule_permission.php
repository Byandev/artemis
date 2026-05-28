<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'Manage Schedule'],
            [
                'category' => 'Teams',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'Manage Schedule')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->leftJoin('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->leftJoin('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where(function ($query) {
                $query
                    ->whereIn('roles.name', ['admin', 'owner'])
                    ->orWhere('permissions.name', 'Edit Teams');
            })
            ->distinct()
            ->pluck('roles.id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->where('name', 'Manage Schedule');
            })
            ->delete();

        DB::table('permissions')
            ->where('name', 'Manage Schedule')
            ->delete();
    }
};
