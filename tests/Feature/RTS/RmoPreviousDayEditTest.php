<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Order;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** A workspace member whose role carries exactly $permissions. */
function rmoMemberWithPermissions(Workspace $workspace, array $permissions): User
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

/** An OrderForDelivery row on $deliveryDate, returned as its id. */
function seedRmoDelivery(Workspace $workspace, Page $page, Shop $shop, string $deliveryDate, array $overrides = []): int
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

/** pancake_users has no factory and no auto-increment — ids come from Pancake. */
function seedRmoPancakeUser(int $id = 9001): int
{
    DB::table('pancake_users')->insert([
        'id' => $id,
        'name' => 'CSR '.$id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function enablePreviousDayEditing(Workspace $workspace): void
{
    $workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        ['enable_edit_previous_day' => true],
    );
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();

    // Five days back: outside the pre-existing "yesterday" carve-out.
    $this->oldDeliveryId = seedRmoDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDays(5)->toDateString(),
    );

    $this->statusUrl = route('public-page.rmo-management.updateStatus', [
        'workspace' => $this->workspace->slug,
        'id' => $this->oldDeliveryId,
    ]);
    $this->assignUrl = route('public-page.rmo-management.assign', [
        'workspace' => $this->workspace->slug,
        'id' => $this->oldDeliveryId,
    ]);
});

test('with the switch off, an old delivery cannot be re-statused', function () {
    $this->post($this->statusUrl, ['status' => 'CALLED'])->assertRedirect();

    expect(DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->status)
        ->toBe('PENDING');
});

test('with the switch off, an old delivery cannot be assigned', function () {
    $assigneeId = seedRmoPancakeUser();

    $this->post($this->assignUrl, ['userId' => $assigneeId])->assertRedirect();

    expect(DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->assignee_id)
        ->toBeNull();
});

test('the switch alone unlocks re-statusing any previous day', function () {
    enablePreviousDayEditing($this->workspace);

    $this->post($this->statusUrl, ['status' => 'CALLED'])->assertRedirect();

    expect(DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->status)
        ->toBe('CALLED');
});

test('the switch alone unlocks assigning any previous day', function () {
    enablePreviousDayEditing($this->workspace);
    $assigneeId = seedRmoPancakeUser();

    $this->post($this->assignUrl, ['userId' => $assigneeId])->assertRedirect();

    expect((int) DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->assignee_id)
        ->toBe($assigneeId);
});

test('the switch alone unlocks bulk assigning any previous day', function () {
    enablePreviousDayEditing($this->workspace);
    $assigneeId = seedRmoPancakeUser();

    $this->post(route('public-page.rmo-management.bulkAssign', ['workspace' => $this->workspace->slug]), [
        'ids' => [$this->oldDeliveryId],
        'userId' => (string) $assigneeId,
    ])->assertRedirect();

    expect((int) DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->assignee_id)
        ->toBe($assigneeId);
});

test('the switch alone unlocks removing an assignee on any previous day', function () {
    enablePreviousDayEditing($this->workspace);
    $assigneeId = seedRmoPancakeUser();
    DB::table('pancake_order_for_delivery')
        ->where('id', $this->oldDeliveryId)
        ->update(['assignee_id' => $assigneeId]);

    $this->post(route('public-page.rmo-management.removeAssignee', [
        'workspace' => $this->workspace->slug,
        'id' => $this->oldDeliveryId,
    ]))->assertRedirect();

    expect(DB::table('pancake_order_for_delivery')->find($this->oldDeliveryId)->assignee_id)
        ->toBeNull();
});

test('the switch opens up every previous date, not merely yesterday', function () {
    enablePreviousDayEditing($this->workspace);

    // Spread across the recent past, well beyond the yesterday carve-out.
    $ids = collect([1, 2, 7, 30, 365])->mapWithKeys(fn ($daysAgo) => [
        $daysAgo => seedRmoDelivery(
            $this->workspace,
            $this->page,
            $this->shop,
            now()->subDays($daysAgo)->toDateString(),
        ),
    ]);

    foreach ($ids as $daysAgo => $id) {
        $this->post(route('public-page.rmo-management.updateStatus', [
            'workspace' => $this->workspace->slug,
            'id' => $id,
        ]), ['status' => 'CALLED'])->assertRedirect();

        expect(DB::table('pancake_order_for_delivery')->find($id)->status)
            ->toBe('CALLED', "delivery from {$daysAgo} day(s) ago should be editable");
    }
});

test('today stays editable regardless of the switch', function () {
    $todayId = seedRmoDelivery($this->workspace, $this->page, $this->shop, now()->toDateString());

    $this->post(route('public-page.rmo-management.updateStatus', [
        'workspace' => $this->workspace->slug,
        'id' => $todayId,
    ]), ['status' => 'CALLED'])->assertRedirect();

    expect(DB::table('pancake_order_for_delivery')->find($todayId)->status)->toBe('CALLED');
});

test('the page hands the switch state to the frontend so the buttons enable', function () {
    // Super admins bypass the public-pages password gate.
    $admin = User::factory()->create(['is_super_admin' => true]);
    $url = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page
            ->where('enable_edit_previous_day', false)
            ->where('can_manage_rmo_settings', true)
        );

    enablePreviousDayEditing($this->workspace);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_edit_previous_day', true));
});

test('the toggle endpoint requires Edit Workspace Settings', function () {
    $url = route('workspaces.rts.rmo-settings.update', ['workspace' => $this->workspace->slug]);

    $member = rmoMemberWithPermissions($this->workspace, [PermissionEnum::ViewRmoManagement->value]);
    $this->actingAs($member)->put($url, ['enable_edit_previous_day' => true])->assertForbidden();

    expect($this->workspace->fresh()->rmoEditPreviousDayEnabled())->toBeFalse();

    $this->actingAs($this->owner)->put($url, ['enable_edit_previous_day' => true])->assertRedirect();

    expect($this->workspace->fresh()->rmoEditPreviousDayEnabled())->toBeTrue();
});
