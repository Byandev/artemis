<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'View Parcel Journey Templates'],
            [
                'category' => 'RTS',
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
                    ->where('name', 'View Parcel Journey Templates');
            })
            ->delete();

        DB::table('permissions')
            ->where('name', 'View Parcel Journey Templates')
            ->delete();
    }
};
