<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Order;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoAutoTag;
use Illuminate\Support\Facades\DB;

/** A workspace member whose role carries exactly $permissions. */
function autoTagMemberWithPermissions(Workspace $workspace, array $permissions): User
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
function seedAutoTagDelivery(Workspace $workspace, Page $page, Shop $shop, string $deliveryDate, array $overrides = []): int
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

function enableAutoTag(Workspace $workspace, array $map): void
{
    $workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        [
            'enable_auto_tag_status' => true,
            'auto_tag_status_map' => $map,
        ],
    );
}

function autoTaggedStatusOf(int $id): string
{
    return DB::table('pancake_order_for_delivery')->find($id)->status;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();
});

test('with auto-tagging off, a delivered parcel resolves to no RMO status', function () {
    expect(RmoAutoTag::statusFor($this->workspace, 'delivered'))->toBeNull();
});

test('a mapped parcel status resolves to its RMO status', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    expect(RmoAutoTag::statusFor($this->workspace->fresh(), 'delivered'))->toBe('DELIVERED');
});

test('an unmapped parcel status is left alone even while auto-tagging is on', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    expect(RmoAutoTag::statusFor($this->workspace->fresh(), 'undeliverable'))->toBeNull();
});

test('parcel statuses match regardless of case or spacing', function () {
    enableAutoTag($this->workspace, ['out_for_delivery' => 'RIDER OTW']);

    $workspace = $this->workspace->fresh();

    expect(RmoAutoTag::statusFor($workspace, 'OUT FOR DELIVERY'))->toBe('RIDER OTW');
    expect(RmoAutoTag::statusFor($workspace, 'Out For Delivery'))->toBe('RIDER OTW');
    expect(RmoAutoTag::statusFor($workspace, 'out_for_delivery'))->toBe('RIDER OTW');
});

test('a map holding unknown statuses is sanitised away rather than tagging junk', function () {
    enableAutoTag($this->workspace, [
        'delivered' => 'DELIVERED',
        'teleported' => 'DELIVERED',   // not a parcel status we know
        'returning' => 'NOT A STATUS', // not an RMO status we know
    ]);

    expect($this->workspace->fresh()->rmoAutoTagStatusMap())->toBe(['delivered' => 'DELIVERED']);
});

test('the command re-tags matching orders and leaves the rest alone', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $deliveredId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered', 'status' => 'RIDER OTW'],
    );

    $inTransitId = seedAutoTagDelivery($this->workspace, $this->page, $this->shop, now()->toDateString());

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($deliveredId))->toBe('DELIVERED');
    expect(autoTaggedStatusOf($inTransitId))->toBe('PENDING');
});

test('the command skips workspaces that have auto-tagging off', function () {
    $id = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($id))->toBe('PENDING');
});

test('the command covers yesterday as well as today by default', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $yesterdayId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDay()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $olderId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDays(5)->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($yesterdayId))->toBe('DELIVERED');
    expect(autoTaggedStatusOf($olderId))->toBe('PENDING');
});

test('--date and --days widen the command to older delivery dates', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $olderId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDays(5)->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->artisan('rmo:apply-auto-tag', ['--date' => now()->subDays(5)->toDateString()])
        ->assertSuccessful();

    expect(autoTaggedStatusOf($olderId))->toBe('DELIVERED');
});

test('--workspace limits the command to a single workspace', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();
    enableAutoTag($otherWorkspace, ['delivered' => 'DELIVERED']);

    $mineId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $theirsId = seedAutoTagDelivery(
        $otherWorkspace,
        $otherPage,
        $otherShop,
        now()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->artisan('rmo:apply-auto-tag', ['--workspace' => $this->workspace->slug])
        ->assertSuccessful();

    expect(autoTaggedStatusOf($mineId))->toBe('DELIVERED');
    expect(autoTaggedStatusOf($theirsId))->toBe('PENDING');
});

test('the command matches parcel statuses regardless of case or spacing', function () {
    enableAutoTag($this->workspace, ['out_for_delivery' => 'RIDER OTW']);

    $id = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'OUT FOR DELIVERY'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($id))->toBe('RIDER OTW');
});

test('re-running the command changes nothing once rows are tagged', function () {
    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $id = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    $touchedAt = DB::table('pancake_order_for_delivery')->find($id)->updated_at;

    $this->artisan('rmo:apply-auto-tag')
        ->expectsOutputToContain('Auto-tagged 0 order(s)')
        ->assertSuccessful();

    expect(autoTaggedStatusOf($id))->toBe('DELIVERED');
    expect(DB::table('pancake_order_for_delivery')->find($id)->updated_at)->toBe($touchedAt);
});

test('saving the settings re-tags today’s matching orders straight away', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $deliveredId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered', 'status' => 'RIDER OTW'],
    );

    $inTransitId = seedAutoTagDelivery($this->workspace, $this->page, $this->shop, now()->toDateString());

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => true,
        'auto_tag_status_map' => ['delivered' => 'DELIVERED'],
    ])->assertRedirect(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]));

    expect(autoTaggedStatusOf($deliveredId))->toBe('DELIVERED');
    expect(autoTaggedStatusOf($inTransitId))->toBe('PENDING');
});

test('the immediate re-tag never reaches another workspace’s orders', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();

    $foreignId = seedAutoTagDelivery(
        $otherWorkspace,
        $otherPage,
        $otherShop,
        now()->toDateString(),
        ['parcel_status' => 'delivered'],
    );

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => true,
        'auto_tag_status_map' => ['delivered' => 'DELIVERED'],
    ])->assertRedirect();

    expect(autoTaggedStatusOf($foreignId))->toBe('PENDING');
});

test('the settings page exposes the auto-tag switch and its map', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $this->actingAs($manager)->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.enable_auto_tag_status', false)
            ->where('settings.auto_tag_status_map', [])
            ->has('parcel_statuses')
            ->has('rmo_statuses')
            ->has('default_auto_tag_map')
        );

    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $this->actingAs($manager)->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('settings.enable_auto_tag_status', true)
            ->where('settings.auto_tag_status_map', ['delivered' => 'DELIVERED'])
        );
});

test('an RMO status outside the known list is rejected by validation', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => true,
        'auto_tag_status_map' => ['delivered' => 'MADE UP STATUS'],
    ])->assertSessionHasErrors('auto_tag_status_map.delivered');
});

test('a save that omits the auto-tag fields leaves the map intact', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    // The other two switches saved on their own must not wipe auto-tagging.
    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => true,
        'enable_bulk_status_update' => false,
    ])->assertRedirect();

    $workspace = $this->workspace->fresh();

    expect($workspace->rmoEditPreviousDayEnabled())->toBeTrue();
    expect($workspace->rmoAutoTagStatusEnabled())->toBeTrue();
    expect($workspace->rmoAutoTagStatusMap())->toBe(['delivered' => 'DELIVERED']);
});

test('saving the auto-tag switch is gated by Manage RMO Settings', function () {
    $outsider = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ViewRmoManagement->value]);

    $this->actingAs($outsider)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => true,
        'auto_tag_status_map' => ['delivered' => 'DELIVERED'],
    ])->assertForbidden();

    expect($this->workspace->fresh()->rmoAutoTagStatusEnabled())->toBeFalse();
});

test('the public page hands the auto-tag state to the frontend', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $url = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_auto_tag_status', false));

    enableAutoTag($this->workspace, ['delivered' => 'DELIVERED']);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page
            ->where('enable_auto_tag_status', true)
            ->where('auto_tag_status_map', ['delivered' => 'DELIVERED'])
        );
});
