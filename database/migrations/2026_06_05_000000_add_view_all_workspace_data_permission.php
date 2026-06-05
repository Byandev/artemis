<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permission = 'View All Workspace Data';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => $this->permission],
            [
                'category' => 'Dashboard',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');

        if (! $permissionId) {
            return;
        }

        // Absence of this permission = the role is restricted to its own team's
        // data. To keep existing access unchanged, back-grant it to every role
        // that is NOT an editor-type role; editor roles stay restricted.
        $roleIds = DB::table('roles')
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%editor%'])
            ->pluck('id');

        $rows = $roleIds
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])
            ->all();

        foreach ($rows as $row) {
            DB::table('role_permissions')->updateOrInsert($row, $row);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->where('name', $this->permission);
            })
            ->delete();

        DB::table('permissions')
            ->where('name', $this->permission)
            ->delete();
    }
};
