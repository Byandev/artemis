<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permission = 'Update Creative Status';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => $this->permission],
            [
                'category' => 'Creatives',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');
        $editId = DB::table('permissions')->where('name', 'Edit Creatives')->value('id');

        if (! $permissionId || ! $editId) {
            return;
        }

        // Preserve current behaviour: any role that can already edit creatives
        // could change their status, so back-grant the new permission to them.
        $roleIds = DB::table('role_permissions')
            ->where('permission_id', $editId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['role_id' => $roleId, 'permission_id' => $permissionId],
            );
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
