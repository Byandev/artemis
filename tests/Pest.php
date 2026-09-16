<?php

use App\Enums\IntegrationService;
use App\Jobs\SyncCsrDailyCallRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserIntegration;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Build the nightly call report across a range, as the scheduler does.
 *
 * The CSR analytics call figures read pancake_user_daily_call_reports, so tests
 * seed call_logs — the way the data arrives — and then run the real job. The
 * previous period is covered too, since every card measures the range against
 * the equally long stretch before it.
 */
function syncCallReport(string $from, string $to): void
{
    $start = CarbonImmutable::parse($from);
    $end = CarbonImmutable::parse($to);
    $cursor = $start->subDays($start->diffInDays($end) + 1);

    while ($cursor->lessThanOrEqualTo($end)) {
        (new SyncCsrDailyCallRecord($cursor->toDateString()))->handle();
        $cursor = $cursor->addDay();
    }
}

/**
 * Create a workspace owned by a fresh user, plus the user attached as 'owner'.
 *
 * @return array{user: User, workspace: Workspace}
 */
function makeWorkspaceWithOwner(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    return ['user' => $user, 'workspace' => $workspace];
}

/**
 * The same, for a Gencys-partner workspace.
 *
 * The partner flag decides where inventory figures come from: a partner's items
 * list and dashboard read frozen `inventory_item_snapshots` rows, and only
 * partners are snapshotted at all. Anything asserting snapshot behaviour needs
 * this rather than makeWorkspaceWithOwner(), which is deliberately live.
 *
 * @return array{user: User, workspace: Workspace}
 */
function makeGencysWorkspaceWithOwner(): array
{
    $made = makeWorkspaceWithOwner();
    $made['workspace']->update(['is_gencys_partner' => true]);

    return $made;
}

/**
 * Give an inventory item a Gencys order feed selling $perDay of it a day.
 *
 * The snapshot measures demand from the order feed rather than from a value
 * typed onto the item, so a test that needs a sales rate has to supply orders.
 * One unit code carrying $perDay of the item, ordered once on each of the
 * three-day window's days — which freezes as units_3d = 3 x $perDay.
 */
function seedDemandFeed(InventoryItem $item, int|float $perDay): void
{
    static $nextOrder = 800000;

    $code = 'UC-'.$item->id;

    DB::table('inventory_unit_codes')->insertOrIgnore([
        'workspace_id' => $item->workspace_id,
        'unit_code' => $code, 'sku' => $code,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('inventory_unit_code_items')->insert([
        'workspace_id' => $item->workspace_id,
        'unit_code' => $code, 'item_code' => $item->sku, 'quantity' => $perDay,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([0, 1, 2] as $daysBack) {
        $id = $nextOrder++;

        DB::table('gencys_orders')->insert([
            'id' => $id, 'workspace_id' => $item->workspace_id, 'order_no' => 'GO-'.$id,
            'order_date' => now()->subDays($daysBack)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gencys_order_items')->insert([
            'order_id' => $id, 'sku' => $code, 'quantity' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/**
 * Create a member user attached to the given workspace with the given pivot role.
 */
function makeWorkspaceMember(Workspace $workspace, string $role = 'member'): User
{
    $user = User::factory()->create();
    $workspace->users()->attach($user->id, ['role' => $role]);

    return $user;
}

/**
 * Create a member attached to the workspace through a role holding exactly the
 * given permissions. Members attached without a role hold nothing, so anything
 * gated on a permission has to be granted one.
 */
function makeMemberWithPermissions(Workspace $workspace, array $permissions, string $category = 'Courses'): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => $category]);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

/**
 * Acting as the owner of a freshly created workspace.
 *
 * @return array{user: User, workspace: Workspace}
 */
function actingAsWorkspaceOwner(): array
{
    $ctx = makeWorkspaceWithOwner();
    test()->actingAs($ctx['user']);

    return $ctx;
}

/**
 * Generate an API key for a workspace and return [model, raw_token].
 *
 * @return array{model: WorkspaceApiKey, raw: string}
 */
function makeApiKey(Workspace $workspace, ?string $name = null): array
{
    $generated = WorkspaceApiKey::generate();
    $model = WorkspaceApiKey::create([
        'workspace_id' => $workspace->id,
        'name' => $name ?? 'Test Key',
        'key' => $generated['key'],
        'key_encrypted' => $generated['key_encrypted'],
        'key_prefix' => $generated['prefix'],
    ]);

    return ['model' => $model, 'raw' => $generated['raw']];
}

/**
 * Give a user a stored Welle token — the only credential that is ever kept.
 *
 * Lives here rather than beside one Welle test because several of them need a
 * connected account, and a helper declared inside a test file only exists for
 * a run that happens to load that file.
 */
function connectWelleAccount(User $user, string $token = 'welle-token-abc'): UserIntegration
{
    return $user->integrations()->updateOrCreate(
        ['service' => IntegrationService::Welle],
        ['token' => $token],
    );
}
