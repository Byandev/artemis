<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the Archive Roles permission exists (replacing the old "Delete Roles").
        DB::table('permissions')->updateOrInsert(
            ['name' => 'Archive Roles'],
            [
                'category' => 'Roles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Add the new Restore Roles permission.
        DB::table('permissions')->updateOrInsert(
            ['name' => 'Restore Roles'],
            [
                'category' => 'Roles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Drop the legacy "Delete Roles" permission. Role assignments cascade
        // automatically via the role_permissions foreign key.
        DB::table('permissions')->where('name', 'Delete Roles')->delete();
    }

    public function down(): void
    {
        // Restore the legacy "Delete Roles" permission.
        DB::table('permissions')->updateOrInsert(
            ['name' => 'Delete Roles'],
            [
                'category' => 'Roles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Remove the Restore Roles permission added by this migration.
        DB::table('permissions')->where('name', 'Restore Roles')->delete();
    }
};
