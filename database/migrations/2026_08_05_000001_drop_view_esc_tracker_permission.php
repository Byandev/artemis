<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The EscTracker module is gone, but its permission row outlived it — the
     * seeder only upserts the cases in App\Enums\Permission, so a stale row
     * keeps showing up in the role editor forever. Drop it, and the role grants
     * pointing at it.
     */
    public function up(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->where('name', 'View ESC Tracker');
            })
            ->delete();

        DB::table('permissions')
            ->where('name', 'View ESC Tracker')
            ->delete();
    }

    /**
     * Irreversible: recreates the permission but not the role grants.
     */
    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'View ESC Tracker'],
            [
                'category' => 'ESC Tracker',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
