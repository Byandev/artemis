<?php

use App\Enums\Permission as PermissionEnum;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The tracker was one "View New Creatives Tracker" grant doing view, add,
     * edit and delete duty. Splitting it lets a role be given the page without
     * being handed the ability to remove other people's tests.
     *
     * Every role that could open the tracker keeps what it could already do —
     * the existing grant is fanned out to the three new ones. Narrowing a role
     * is then a deliberate edit in the role editor rather than something that
     * happens to people on deploy.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $viewId = DB::table('permissions')
            ->where('name', PermissionEnum::ViewNewCreativesTracker->value)
            ->value('id');

        if ($viewId === null) {
            return;
        }

        $roleIds = DB::table('role_permissions')->where('permission_id', $viewId)->pluck('role_id');

        $newIds = DB::table('permissions')->whereIn('name', [
            PermissionEnum::CreateNewCreativesTracker->value,
            PermissionEnum::EditNewCreativesTracker->value,
            PermissionEnum::DeleteNewCreativesTracker->value,
        ])->pluck('id');

        $grants = [];

        foreach ($roleIds as $roleId) {
            foreach ($newIds as $newId) {
                $grants[] = ['role_id' => $roleId, 'permission_id' => $newId];
            }
        }

        foreach (array_chunk($grants, 500) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Deliberately empty: rolling back must not strip a role of its grants.
    }
};
