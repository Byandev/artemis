<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * The migration that split "View Sales & Marketing Dashboard" into five.
 *
 * The suite migrates a fresh database, so by the time a test runs there is no
 * old grant left to convert — which means the fan-out, the one part of this
 * change that can silently lock people out on deploy, would never be exercised.
 * These rebuild the pre-split state by hand and run the migration over it.
 */
function splitMigration(): object
{
    return require database_path(
        'migrations/2026_08_28_000001_split_sales_marketing_dashboard_permission.php'
    );
}

/** A role holding the retired umbrella permission, as production has it. */
function roleWithOldDashboardGrant(): Role
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $old = Permission::firstOrCreate(
        ['name' => 'View Sales & Marketing Dashboard'],
        ['category' => 'Dashboards'],
    );

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Advertiser']);

    DB::table('role_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $old->id,
    ]);

    return $role;
}

/** @return array<int, string> */
function grantedNames(Role $role): array
{
    return DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $role->id)
        ->orderBy('permissions.name')
        ->pluck('permissions.name')
        ->all();
}

test('a role that could open the dashboard keeps all five pages', function () {
    $role = roleWithOldDashboardGrant();

    splitMigration()->up();

    expect(grantedNames($role))->toBe([
        'View Ad Spend Goals',
        'View Adspent Summary',
        'View Page ROAS Tracker',
        'View S&M Daily Report',
        'View Sales Targets',
    ]);
});

test('the retired permission and its grants are dropped', function () {
    $role = roleWithOldDashboardGrant();

    splitMigration()->up();

    expect(grantedNames($role))->not->toContain('View Sales & Marketing Dashboard')
        ->and(Permission::where('name', 'View Sales & Marketing Dashboard')->exists())->toBeFalse();
});

test('a role that already held one of the five does not get a duplicate', function () {
    $role = roleWithOldDashboardGrant();

    // The pivot has no unique constraint, so the insertOrIgnore has to not
    // re-insert a pair the role already has — otherwise revoking it in the role
    // editor would leave a second row behind still granting it.
    $existing = Permission::firstOrCreate(
        ['name' => 'View Ad Spend Goals'],
        ['category' => 'Ad Spend Goals'],
    );

    DB::table('role_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $existing->id,
    ]);

    splitMigration()->up();

    $adSpendGoalGrants = DB::table('role_permissions')
        ->where('role_id', $role->id)
        ->where('permission_id', $existing->id)
        ->count();

    expect($adSpendGoalGrants)->toBe(1);
});

test('a role that never had the dashboard gains nothing', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Bookkeeper']);

    splitMigration()->up();

    expect(grantedNames($role))->toBe([]);
});

test('it is safe to run when the old permission is already gone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Advertiser']);

    splitMigration()->up();
    splitMigration()->up();

    expect(grantedNames($role))->toBe([]);
});
