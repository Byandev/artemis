<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sales & Marketing was one tabbed dashboard behind one grant, "View Sales
     * & Marketing Dashboard". It is now five sibling pages, each with its own.
     *
     * Two of the five already had permissions from when they were standalone
     * pages ("View Ad Spend Goals", "View Adspent Summary"); three are new.
     *
     * Every role that could open the dashboard keeps being able to open all of
     * it — the old grant is fanned out to the five before it is dropped. Nobody
     * loses access on deploy; narrowing a role is then a deliberate edit in the
     * role editor.
     */
    private const OLD = 'View Sales & Marketing Dashboard';

    private const NEW = [
        'View S&M Daily Report',
        'View Page ROAS Tracker',
        'View Ad Spend Goals',
        'View Adspent Summary',
        'View Sales Targets',
    ];

    public function up(): void
    {
        // Land the three new enum cases in the permissions table first, so the
        // fan-out below has rows to point at.
        (new PermissionSeeder)->run();

        $oldId = DB::table('permissions')->where('name', self::OLD)->value('id');

        if ($oldId !== null) {
            $roleIds = DB::table('role_permissions')
                ->where('permission_id', $oldId)
                ->pluck('role_id');

            $newIds = DB::table('permissions')
                ->whereIn('name', self::NEW)
                ->pluck('id');

            $grants = [];

            foreach ($roleIds as $roleId) {
                foreach ($newIds as $newId) {
                    $grants[] = ['role_id' => $roleId, 'permission_id' => $newId];
                }
            }

            // insertOrIgnore: a role may already hold "View Ad Spend Goals" on
            // its own, and the pivot is unique on the pair.
            foreach (array_chunk($grants, 500) as $chunk) {
                DB::table('role_permissions')->insertOrIgnore($chunk);
            }

            DB::table('role_permissions')->where('permission_id', $oldId)->delete();
            DB::table('permissions')->where('id', $oldId)->delete();
        }
    }

    /**
     * Irreversible: restores the permission row, but which roles held it is not
     * recoverable once the five have been granted on their own merits.
     */
    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => self::OLD],
            ['category' => 'Dashboards', 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
