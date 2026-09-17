<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Order;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoAutoAssign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/** A workspace member whose role carries exactly $permissions. */
function autoAssignMemberWithPermissions(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'RTS']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

/** A Pancake CSR attached to $shop, which is what makes them assignable. */
function autoAssignCsr(Shop $shop, string $name): PancakeUser
{
    $csr = PancakeUser::create(['name' => $name]);

    // The pivot carries a UUID primary key of its own, so attach() can't fill it.
    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shop->id,
        'user_id' => $csr->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $csr;
}

/** An OrderForDelivery row on $deliveryDate, returned as its id. */
function seedAutoAssignDelivery(Workspace $workspace, Page $page, Shop $shop, string $deliveryDate, array $overrides = []): int
{
    $order = Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
    ]);

    return DB::table('pancake_order_for_delivery')->insertGetId(array_merge([
        'order_id' => $order->id,
        'page_id' => $page->id,
        'shop_id' => $shop->id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'in_transit',
        'rider_name' => 'Rider',
        'rider_phone' => '+1',
        'delivery_date' => $deliveryDate,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

/** Switch auto-assignment on, pointed at $csr — or at nobody when omitted. */
function enableAutoAssign(Workspace $workspace, ?PancakeUser $csr = null): void
{
    $workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        [
            'enable_auto_assign' => true,
            'auto_assign_user_id' => $csr ? (string) $csr->id : null,
        ],
    );
}

function assigneeOf(int $id): ?string
{
    return DB::table('pancake_order_for_delivery')->find($id)->assignee_id;
}

/** How many of $date's rows each CSR ended up holding, keyed by name. */
function assignmentCounts(Workspace $workspace, string $date): array
{
    return DB::table('pancake_order_for_delivery')
        ->join('pancake_users', 'pancake_users.id', '=', 'pancake_order_for_delivery.assignee_id')
        ->where('pancake_order_for_delivery.workspace_id', $workspace->id)
        ->whereDate('pancake_order_for_delivery.delivery_date', $date)
        ->groupBy('pancake_users.name')
        ->pluck(DB::raw('COUNT(*)'), 'pancake_users.name')
        ->map(fn ($count) => (int) $count)
        ->all();
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();
    $this->today = now()->toDateString();
});

test('nobody is assigned to until a CSR is configured', function () {
    expect(RmoAutoAssign::assignee($this->workspace))->toBeNull();
});

test('a CSR who is not on a workspace shop is ignored', function () {
    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $theirs = autoAssignCsr(Shop::factory()->forWorkspace($otherWorkspace)->create(), 'Theirs');

    enableAutoAssign($this->workspace, $theirs);

    expect(RmoAutoAssign::assignee($this->workspace->fresh()))->toBeNull();
});

test('with auto-assignment off, unassigned orders are left alone', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');

    $this->workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $this->workspace->id],
        ['enable_auto_assign' => false, 'auto_assign_user_id' => (string) $csr->id],
    );

    $id = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(0);
    expect(assigneeOf($id))->toBeNull();
});

test('switched on with nobody set, nothing is assigned', function () {
    enableAutoAssign($this->workspace);

    $id = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(0);
    expect(assigneeOf($id))->toBeNull();
});

test('every unassigned order on the day goes to the one configured CSR', function () {
    $ana = autoAssignCsr($this->shop, 'Ana');

    // Jun is assignable but not the one configured, so nothing lands on him.
    autoAssignCsr($this->shop, 'Jun');

    enableAutoAssign($this->workspace, $ana);

    foreach (range(1, 6) as $ignored) {
        seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);
    }

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(6);
    expect(assignmentCounts($this->workspace, $this->today))->toBe(['Ana' => 6]);
});

test('an assignee set by hand is never overwritten', function () {
    $ana = autoAssignCsr($this->shop, 'Ana');
    $jun = autoAssignCsr($this->shop, 'Jun');

    enableAutoAssign($this->workspace, $ana);

    $claimed = seedAutoAssignDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        $this->today,
        ['assignee_id' => $jun->id],
    );

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(0);
    expect(assigneeOf($claimed))->toBe((string) $jun->id);
});

test('rows another CSR already holds stay with them', function () {
    $ana = autoAssignCsr($this->shop, 'Ana');
    $jun = autoAssignCsr($this->shop, 'Jun');

    enableAutoAssign($this->workspace, $ana);

    // Jun claimed three by hand; three more arrive unclaimed.
    foreach (range(1, 3) as $ignored) {
        seedAutoAssignDelivery(
            $this->workspace,
            $this->page,
            $this->shop,
            $this->today,
            ['assignee_id' => $jun->id],
        );
    }

    foreach (range(1, 3) as $ignored) {
        seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);
    }

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(3);

    // Key order follows the group-by, so compare the counts, not the shape.
    expect(assignmentCounts($this->workspace, $this->today))
        ->toEqualCanonicalizing(['Ana' => 3, 'Jun' => 3]);
});

test('another workspace\'s orders are not touched', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();

    $theirs = seedAutoAssignDelivery($otherWorkspace, $otherPage, $otherShop, $this->today);

    RmoAutoAssign::apply($this->workspace->fresh(), $this->today);

    expect(assigneeOf($theirs))->toBeNull();
});

test('re-running assigns nothing once every row is taken', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);

    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(1);
    expect(RmoAutoAssign::apply($this->workspace->fresh(), $this->today))->toBe(0);
});

test('the command assigns for opted-in workspaces and skips the rest', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();

    $mine = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);
    $theirs = seedAutoAssignDelivery($otherWorkspace, $otherPage, $otherShop, $this->today);

    $this->artisan('rmo:apply-auto-assign')->assertSuccessful();

    expect(assigneeOf($mine))->toBe((string) $csr->id);
    expect(assigneeOf($theirs))->toBeNull();
});

test('the command covers yesterday as well as today by default', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $yesterday = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, now()->subDay()->toDateString());
    $older = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, now()->subDays(5)->toDateString());

    $this->artisan('rmo:apply-auto-assign')->assertSuccessful();

    expect(assigneeOf($yesterday))->toBe((string) $csr->id);
    expect(assigneeOf($older))->toBeNull();
});

test('--date widens the command to an older delivery date', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $older = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, now()->subDays(5)->toDateString());

    $this->artisan('rmo:apply-auto-assign', ['--date' => now()->subDays(5)->toDateString()])
        ->assertSuccessful();

    expect(assigneeOf($older))->toBe((string) $csr->id);
});

test('--workspace limits the command to a single workspace', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();
    $otherCsr = autoAssignCsr($otherShop, 'Jun');
    enableAutoAssign($otherWorkspace, $otherCsr);

    $mine = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);
    $theirs = seedAutoAssignDelivery($otherWorkspace, $otherPage, $otherShop, $this->today);

    $this->artisan('rmo:apply-auto-assign', ['--workspace' => $this->workspace->slug])
        ->assertSuccessful();

    expect(assigneeOf($mine))->toBe((string) $csr->id);
    expect(assigneeOf($theirs))->toBeNull();
});

test('the command warns when the switch is on but nobody is set', function () {
    enableAutoAssign($this->workspace);

    $this->artisan('rmo:apply-auto-assign')
        ->expectsOutputToContain('no CSR is set')
        ->assertSuccessful();
});

test('the settings page offers the workspace\'s CSRs and saves the one picked', function () {
    $ana = autoAssignCsr($this->shop, 'Ana');

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => false,
            'enable_bulk_status_update' => false,
            'enable_auto_assign' => true,
            'auto_assign_user_id' => (string) $ana->id,
        ])
        ->assertRedirect();

    expect($this->workspace->fresh()->rmoAutoAssignEnabled())->toBeTrue();
    expect(RmoAutoAssign::assignee($this->workspace->fresh()))->toBe((string) $ana->id);
});

test('the settings page rejects more than one CSR', function () {
    $ana = autoAssignCsr($this->shop, 'Ana');
    $jun = autoAssignCsr($this->shop, 'Jun');

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => false,
            'enable_bulk_status_update' => false,
            'enable_auto_assign' => true,
            'auto_assign_user_id' => [(string) $ana->id, (string) $jun->id],
        ])
        ->assertSessionHasErrors('auto_assign_user_id');

    expect(RmoAutoAssign::assignee($this->workspace->fresh()))->toBeNull();
});

test('the settings page rejects a CSR from outside the workspace', function () {
    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $outsider = autoAssignCsr(Shop::factory()->forWorkspace($otherWorkspace)->create(), 'Outsider');

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => false,
            'enable_bulk_status_update' => false,
            'enable_auto_assign' => true,
            'auto_assign_user_id' => (string) $outsider->id,
        ])
        ->assertSessionHasErrors('auto_assign_user_id');

    expect($this->workspace->fresh()->rmoAutoAssignEnabled())->toBeFalse();
});

test('an empty picker clears the CSR rather than failing validation', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => false,
            'enable_bulk_status_update' => false,
            'enable_auto_assign' => true,
            'auto_assign_user_id' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(RmoAutoAssign::assignee($this->workspace->fresh()))->toBeNull();
});

test('saving the switch assigns today\'s unassigned orders straight away', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');

    $id = seedAutoAssignDelivery($this->workspace, $this->page, $this->shop, $this->today);

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => false,
            'enable_bulk_status_update' => false,
            'enable_auto_assign' => true,
            'auto_assign_user_id' => (string) $csr->id,
        ])
        ->assertRedirect();

    expect(assigneeOf($id))->toBe((string) $csr->id);
});

test('a payload without the auto-assign fields leaves them as they were', function () {
    $csr = autoAssignCsr($this->shop, 'Ana');
    enableAutoAssign($this->workspace, $csr);

    $member = autoAssignMemberWithPermissions($this->workspace, [
        PermissionEnum::ManageRmoSettings->value,
    ]);

    $this->actingAs($member)
        ->put("/workspaces/{$this->workspace->slug}/settings/rmo", [
            'enable_edit_previous_day' => true,
            'enable_bulk_status_update' => false,
        ])
        ->assertRedirect();

    $workspace = $this->workspace->fresh();

    expect($workspace->rmoAutoAssignEnabled())->toBeTrue();
    expect(RmoAutoAssign::assignee($workspace))->toBe((string) $csr->id);
});
