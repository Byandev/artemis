<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "View All Workspace Data" is the bypass for team-level visibility scoping.
     * To preserve current behaviour on deploy (everyone currently sees the whole
     * workspace), grant it to every existing role. Admins then remove it from the
     * roles they want scoped (e.g. CSR).
     */
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'View All Workspace Data'],
            [
                'category' => 'Data Access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'View All Workspace Data')
            ->value('id');

        $rows = DB::table('roles')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])
            ->all();

        if (! empty($rows)) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->where('name', 'View All Workspace Data');
            })
            ->delete();

        DB::table('permissions')
            ->where('name', 'View All Workspace Data')
            ->delete();
    }
};
