<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'View Botcake Sequences',
            'View Botcake Flows',
        ] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'category' => 'Botcake',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->whereIn('name', [
                        'View Botcake Sequences',
                        'View Botcake Flows',
                    ]);
            })
            ->delete();

        DB::table('permissions')
            ->whereIn('name', [
                'View Botcake Sequences',
                'View Botcake Flows',
            ])
            ->delete();
    }
};
