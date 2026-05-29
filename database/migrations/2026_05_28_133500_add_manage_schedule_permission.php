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
