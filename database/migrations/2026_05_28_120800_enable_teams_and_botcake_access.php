<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        // Keep access decisions made after this migration intact.
    }
};
