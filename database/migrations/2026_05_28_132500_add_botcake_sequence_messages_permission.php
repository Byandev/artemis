<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'View Botcake Sequence Messages'],
            [
                'category' => 'Botcake',
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
                    ->where('name', 'View Botcake Sequence Messages');
            })
            ->delete();

        DB::table('permissions')
            ->where('name', 'View Botcake Sequence Messages')
            ->delete();
    }
};
