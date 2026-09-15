<?php

use App\Enums\Permission;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The "My Pancake Users" page.
 *
 * The CSR dashboard sums these pancake users' rollup rows without ever naming
 * them, so this page is the legend for it. Gated and scoped like the dashboard
 * it explains — see CSRController::pancakeUsers().
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->workspace->update(['csr_dashboard_module_enabled' => true]);
    $this->shop = Shop::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Main Shop',
    ]);
});

/** A pancake account, optionally on shops and optionally linked to a user. */
function pageCsr(string $name, ?User $systemUser = null, array $shopIds = [], array $attributes = []): PancakeUser
{
    $csr = PancakeUser::create([
        'name' => $name,
        'user_id' => $systemUser?->id,
        ...$attributes,
    ]);

    foreach ($shopIds as $shopId) {
        // The pivot carries a uuid primary key of its own, hence the insert.
        DB::table('pancake_shop_users')->insert([
            'id' => (string) Str::uuid(),
            'shop_id' => $shopId,
            'user_id' => $csr->id,
        ]);
    }

    return $csr;
}

function visitPancakeUsersPage(User $user, Workspace $workspace)
{
    return test()->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/csr/pancake-users");
}

function pageUsers(User $user, Workspace $workspace): array
{
    $response = visitPancakeUsersPage($user, $workspace);
    $response->assertOk();

    return $response->viewData('page')['props']['pancakeUsers'];
}

it('lists the pancake users linked to the signed-in user, with their shops', function () {
    $second = Shop::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Side Shop',
    ]);

    pageCsr('Zoe Reyes', $this->owner, [$this->shop->id, $second->id], [
        'email' => 'zoe@example.com',
        'phone_number' => '639170000000',
        'fb_id' => '1234567890',
    ]);
    pageCsr('Ana Cruz', $this->owner, [$this->shop->id], ['status' => 'INACTIVE']);

    $users = pageUsers($this->owner, $this->workspace);

    // Ordered by name, so the page reads the same on every load.
    expect(array_column($users, 'name'))->toBe(['Ana Cruz', 'Zoe Reyes']);
    expect($users[0]['status'])->toBe('INACTIVE');

    $zoe = $users[1];
    expect($zoe['email'])->toBe('zoe@example.com');
    expect($zoe['phone_number'])->toBe('639170000000');
    expect($zoe['fb_id'])->toBe('1234567890');
    expect(array_column($zoe['shops'], 'name'))->toBe(['Main Shop', 'Side Shop']);
});

it('leaves out pancake users linked to somebody else, or to no one', function () {
    pageCsr('Mine', $this->owner, [$this->shop->id]);
    pageCsr('Theirs', User::factory()->create(), [$this->shop->id]);
    pageCsr('Unlinked', null, [$this->shop->id]);

    expect(array_column(pageUsers($this->owner, $this->workspace), 'name'))
        ->toBe(['Mine']);
});

it('leaves out a pancake user that works no shop of this workspace', function () {
    $elsewhere = Shop::factory()->create([
        'workspace_id' => Workspace::factory()->forOwner(User::factory()->create())->create()->id,
    ]);

    pageCsr('Here', $this->owner, [$this->shop->id]);
    pageCsr('Elsewhere', $this->owner, [$elsewhere->id]);
    pageCsr('Unplaced', $this->owner);

    expect(array_column(pageUsers($this->owner, $this->workspace), 'name'))
        ->toBe(['Here']);
});

it('lists only this workspace\'s shops against a pancake user working several', function () {
    $otherWorkspace = Workspace::factory()->forOwner(User::factory()->create())->create();
    $elsewhere = Shop::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Workspace Shop',
    ]);

    pageCsr('Zoe Reyes', $this->owner, [$this->shop->id, $elsewhere->id]);

    $users = pageUsers($this->owner, $this->workspace);

    expect(array_column($users[0]['shops'], 'name'))->toBe(['Main Shop']);
});

it('reports the later of the two rollups as the last active day', function () {
    $csr = pageCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    PancakeUserPosDailyReport::create([
        'workspace_id' => $this->workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $this->shop->id,
        'date' => '2026-08-10',
        'total_sales' => 1000,
        'total_orders' => 4,
    ]);
    // The call rollup runs later than the POS one here, so it decides the day.
    PancakeUserDailyCallReport::create([
        'workspace_id' => $this->workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $this->shop->id,
        'date' => '2026-08-14',
        'total_called' => 9,
    ]);

    expect(pageUsers($this->owner, $this->workspace)[0]['last_active_on'])
        ->toBe('2026-08-14');
});

it('reports no last active day for a pancake user with no rollup rows', function () {
    pageCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    expect(pageUsers($this->owner, $this->workspace)[0]['last_active_on'])
        ->toBeNull();
});

it('leaves out another workspace\'s rollup rows when dating a pancake user', function () {
    $otherWorkspace = Workspace::factory()->forOwner(User::factory()->create())->create();
    $elsewhere = Shop::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $csr = pageCsr('Zoe Reyes', $this->owner, [$this->shop->id, $elsewhere->id]);

    PancakeUserPosDailyReport::create([
        'workspace_id' => $this->workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $this->shop->id,
        'date' => '2026-08-10',
        'total_sales' => 1000,
        'total_orders' => 4,
    ]);
    PancakeUserPosDailyReport::create([
        'workspace_id' => $otherWorkspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $elsewhere->id,
        'date' => '2026-09-30',
        'total_sales' => 1000,
        'total_orders' => 4,
    ]);

    expect(pageUsers($this->owner, $this->workspace)[0]['last_active_on'])
        ->toBe('2026-08-10');
});

it('renders an empty list for a user with no linked pancake user', function () {
    pageCsr('Somebody Else', User::factory()->create(), [$this->shop->id]);

    expect(pageUsers($this->owner, $this->workspace))->toBe([]);
});

it('renders the pancake users page', function () {
    pageCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    $response = visitPancakeUsersPage($this->owner, $this->workspace);

    $response->assertOk();
    expect($response->viewData('page')['component'])
        ->toBe('workspaces/csr/pancake-users');
});

it('disappears with the CSR dashboard module', function () {
    pageCsr('Zoe Reyes', $this->owner, [$this->shop->id]);
    $this->workspace->update(['csr_dashboard_module_enabled' => false]);

    visitPancakeUsersPage($this->owner, $this->workspace)->assertNotFound();
});

it('lets a member holding the dashboard permission through', function () {
    $member = makeMemberWithPermissions(
        $this->workspace,
        [Permission::ViewCsrDashboard->value],
        'CSR',
    );
    pageCsr('Their Account', $member, [$this->shop->id]);

    expect(array_column(pageUsers($member, $this->workspace), 'name'))
        ->toBe(['Their Account']);
});

it('turns away a member without the dashboard permission', function () {
    $member = makeWorkspaceMember($this->workspace);

    visitPancakeUsersPage($member, $this->workspace)->assertForbidden();
});

it('turns away a non-member', function () {
    visitPancakeUsersPage(User::factory()->create(), $this->workspace)
        ->assertForbidden();
});
