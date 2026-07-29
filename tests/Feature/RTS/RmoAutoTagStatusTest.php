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

function enableAutoTag(Workspace $workspace): void
{
    $workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        ['enable_auto_tag_status' => true],
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

test('with auto-tagging off, neither parcel status resolves to an RMO status', function () {
    expect(RmoAutoTag::statusFor($this->workspace, 'delivered'))->toBeNull();
    expect(RmoAutoTag::statusFor($this->workspace, 'returning'))->toBeNull();
});

test('delivered tags to DELIVERED and returning to RETURNING', function () {
    enableAutoTag($this->workspace);

    $workspace = $this->workspace->fresh();

    expect(RmoAutoTag::statusFor($workspace, 'delivered'))->toBe('DELIVERED');
    expect(RmoAutoTag::statusFor($workspace, 'returning'))->toBe('RETURNING');
});

test('every other parcel status is left under CSR control', function () {
    enableAutoTag($this->workspace);

    $workspace = $this->workspace->fresh();

    foreach (['returned', 'undeliverable', 'out_for_delivery', 'in_transit', 'cancelled'] as $parcelStatus) {
        expect(RmoAutoTag::statusFor($workspace, $parcelStatus))->toBeNull();
    }
});

test('parcel statuses match regardless of case or spacing', function () {
    enableAutoTag($this->workspace);

    $workspace = $this->workspace->fresh();

    expect(RmoAutoTag::statusFor($workspace, 'DELIVERED'))->toBe('DELIVERED');
    expect(RmoAutoTag::statusFor($workspace, ' Delivered '))->toBe('DELIVERED');
    expect(RmoAutoTag::statusFor($workspace, 'Returning'))->toBe('RETURNING');
});

test('the command tags both statuses and leaves the rest alone', function () {
    enableAutoTag($this->workspace);

    $deliveredId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'delivered', 'status' => 'RIDER OTW'],
    );

    $returningId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'returning'],
    );

    $untouchedId = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'undeliverable'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($deliveredId))->toBe('DELIVERED');
    expect(autoTaggedStatusOf($returningId))->toBe('RETURNING');
    expect(autoTaggedStatusOf($untouchedId))->toBe('PENDING');
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
    enableAutoTag($this->workspace);

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

test('--date widens the command to an older delivery date', function () {
    enableAutoTag($this->workspace);

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
    enableAutoTag($this->workspace);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();
    enableAutoTag($otherWorkspace);

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

test('the command matches a parcel status stored in upper case', function () {
    enableAutoTag($this->workspace);

    $id = seedAutoTagDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->toDateString(),
        ['parcel_status' => 'DELIVERED'],
    );

    $this->artisan('rmo:apply-auto-tag')->assertSuccessful();

    expect(autoTaggedStatusOf($id))->toBe('DELIVERED');
});

test('re-running the command changes nothing once rows are tagged', function () {
    enableAutoTag($this->workspace);

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
    ])->assertRedirect();

    expect(autoTaggedStatusOf($foreignId))->toBe('PENDING');
});

test('the settings page exposes and saves the auto-tag switch', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $this->actingAs($manager)->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('settings.enable_auto_tag_status', false));

    enableAutoTag($this->workspace);

    $this->actingAs($manager)->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertInertia(fn ($page) => $page->where('settings.enable_auto_tag_status', true));

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => false,
    ])->assertRedirect();

    expect($this->workspace->fresh()->rmoAutoTagStatusEnabled())->toBeFalse();
});

test('a save that omits the auto-tag field leaves the switch alone', function () {
    $manager = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    enableAutoTag($this->workspace);

    // The other two switches saved on their own must not turn auto-tagging off.
    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => true,
        'enable_bulk_status_update' => false,
    ])->assertRedirect();

    $workspace = $this->workspace->fresh();

    expect($workspace->rmoEditPreviousDayEnabled())->toBeTrue();
    expect($workspace->rmoAutoTagStatusEnabled())->toBeTrue();
});

test('saving the auto-tag switch is gated by Manage RMO Settings', function () {
    $outsider = autoTagMemberWithPermissions($this->workspace, [PermissionEnum::ViewRmoManagement->value]);

    $this->actingAs($outsider)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_auto_tag_status' => true,
    ])->assertForbidden();

    expect($this->workspace->fresh()->rmoAutoTagStatusEnabled())->toBeFalse();
});

test('the public page hands the auto-tag state to the frontend', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $url = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_auto_tag_status', false));

    enableAutoTag($this->workspace);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_auto_tag_status', true));
});
