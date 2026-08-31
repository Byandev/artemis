<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Sales & Marketing used to be one tabbed dashboard behind one grant. Now that
 * it is a group of sibling pages, each page checks its own — so a role can be
 * given the Daily Report without also being handed everyone's sales targets.
 *
 * These pin that the gates are actually independent, in both directions: the
 * page you were granted opens, and the others you weren't do not.
 */

/** A workspace with the S&M pages switched on, plus a team to target. */
function smPermissionContext(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update([
        'sales_marketing_dashboard_module_enabled' => true,
        'ad_spend_goals_module_enabled' => true,
    ]);

    Team::factory()->create(['workspace_id' => $workspace->id]);

    return $workspace;
}

/** A member of $workspace holding exactly $permissions and nothing else. */
function smMemberWith(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $permission) {
        $row = Permission::firstOrCreate(
            ['name' => $permission->value],
            ['category' => $permission->category()],
        );

        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $row->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

/** Each page, with the one permission that opens it. */
dataset('sm_pages', [
    'dashboard' => ['dashboard', PermissionEnum::ViewSalesMarketingDashboard],
    'daily report' => ['daily-report', PermissionEnum::ViewSalesMarketingDailyReport],
    'page roas tracker' => ['page-roas-tracker', PermissionEnum::ViewPageRoasTracker],
    'ad spend goals' => ['ad-spend-goals', PermissionEnum::ViewAdSpendGoals],
    'ad spent summary' => ['ad-spent-summary', PermissionEnum::ViewAdSpentSummary],
    'sales targets' => ['sales-targets', PermissionEnum::ViewSalesTargets],
]);

test('the page opens for a role holding only its own permission', function (string $path, PermissionEnum $permission) {
    $workspace = smPermissionContext();

    $this->actingAs(smMemberWith($workspace, [$permission]))
        ->get("/workspaces/{$workspace->slug}/sales-marketing/{$path}")
        ->assertOk();
})->with('sm_pages');

test('that permission opens no other page in the group', function (string $path, PermissionEnum $permission) {
    $workspace = smPermissionContext();
    $user = smMemberWith($workspace, [$permission]);

    $others = collect([
        'dashboard',
        'daily-report',
        'page-roas-tracker',
        'ad-spend-goals',
        'ad-spent-summary',
        'sales-targets',
    ])->reject(fn (string $other) => $other === $path);

    foreach ($others as $other) {
        $this->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/sales-marketing/{$other}")
            ->assertForbidden();
    }
})->with('sm_pages');

test('a member with none of them is refused everywhere', function (string $path) {
    $workspace = smPermissionContext();

    $this->actingAs(smMemberWith($workspace, []))
        ->get("/workspaces/{$workspace->slug}/sales-marketing/{$path}")
        ->assertForbidden();
})->with([
    'dashboard',
    'daily-report',
    'page-roas-tracker',
    'ad-spend-goals',
    'ad-spent-summary',
    'sales-targets',
]);

test('the module switch hides every grant in the group from the role editor', function () {
    $workspace = smPermissionContext();

    expect($workspace->hiddenPermissionNames())->not->toContain(
        PermissionEnum::ViewSalesMarketingDailyReport->value,
        PermissionEnum::ViewAdSpendGoals->value,
    );

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    expect($workspace->fresh()->hiddenPermissionNames())->toContain(
        PermissionEnum::ViewSalesMarketingDashboard->value,
        PermissionEnum::ViewSalesMarketingDailyReport->value,
        PermissionEnum::ViewPageRoasTracker->value,
        PermissionEnum::ViewSalesTargets->value,
        PermissionEnum::ViewAdSpentSummary->value,
        PermissionEnum::ViewAdSpendGoals->value,
    );
});

test('the ad spend goals switch hides only its own grant', function () {
    $workspace = smPermissionContext();

    $workspace->update(['ad_spend_goals_module_enabled' => false]);

    $hidden = $workspace->fresh()->hiddenPermissionNames();

    expect($hidden)->toContain(PermissionEnum::ViewAdSpendGoals->value)
        ->and($hidden)->not->toContain(PermissionEnum::ViewSalesMarketingDailyReport->value);
});

test('the retired umbrella permission is gone from the enum', function () {
    // Kept as a test rather than a comment: the migration drops the row, and a
    // case sneaking back would silently resurrect it in the role editor.
    $names = array_map(fn (PermissionEnum $p) => $p->value, PermissionEnum::cases());

    expect($names)->not->toContain('View Sales & Marketing Dashboard');
});
