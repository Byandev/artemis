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
function bulkStatusMemberWithPermissions(Workspace $workspace, array $permissions): User
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
function seedBulkStatusDelivery(Workspace $workspace, Page $page, Shop $shop, string $deliveryDate, array $overrides = []): int
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

function enableBulkStatusUpdate(Workspace $workspace, bool $previousDays = false): void
{
    $workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        [
            'enable_bulk_status_update' => true,
            'enable_edit_previous_day' => $previousDays,
        ],
    );
}

function statusOf(int $id): string
{
    return DB::table('pancake_order_for_delivery')->find($id)->status;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    subscribeWorkspace($this->workspace);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();

    $this->todayIds = [
        seedBulkStatusDelivery($this->workspace, $this->page, $this->shop, now()->toDateString()),
        seedBulkStatusDelivery($this->workspace, $this->page, $this->shop, now()->toDateString()),
    ];

    $this->bulkUrl = route('public-page.rmo-management.bulkUpdateStatus', [
        'workspace' => $this->workspace->slug,
    ]);
});

test('with the switch off, bulk status update changes nothing', function () {
    $this->post($this->bulkUrl, ['ids' => $this->todayIds, 'status' => 'DELIVERED'])
        ->assertRedirect()
        ->assertSessionHas('error');

    foreach ($this->todayIds as $id) {
        expect(statusOf($id))->toBe('PENDING');
    }
});

test('with the switch on, every selected order of today is re-statused at once', function () {
    enableBulkStatusUpdate($this->workspace);

    $this->post($this->bulkUrl, ['ids' => $this->todayIds, 'status' => 'DELIVERED'])
        ->assertRedirect()
        ->assertSessionHas('success');

    foreach ($this->todayIds as $id) {
        expect(statusOf($id))->toBe('DELIVERED');
    }
});

test('bulk status update skips orders outside the editable date window', function () {
    enableBulkStatusUpdate($this->workspace);

    $oldId = seedBulkStatusDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDays(5)->toDateString(),
    );

    $this->post($this->bulkUrl, [
        'ids' => [...$this->todayIds, $oldId],
        'status' => 'DELIVERED',
    ])->assertRedirect();

    expect(statusOf($this->todayIds[0]))->toBe('DELIVERED');
    expect(statusOf($oldId))->toBe('PENDING');
});

test('previous-day editing widens bulk status update to older deliveries', function () {
    enableBulkStatusUpdate($this->workspace, previousDays: true);

    $oldId = seedBulkStatusDelivery(
        $this->workspace,
        $this->page,
        $this->shop,
        now()->subDays(5)->toDateString(),
    );

    $this->post($this->bulkUrl, ['ids' => [$oldId], 'status' => 'DELIVERED'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(statusOf($oldId))->toBe('DELIVERED');
});

test('bulk status update never reaches another workspace’s orders', function () {
    enableBulkStatusUpdate($this->workspace);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $otherPage = Page::factory()->forWorkspace($otherWorkspace)->create();
    $otherShop = Shop::factory()->forWorkspace($otherWorkspace)->create();
    $foreignId = seedBulkStatusDelivery($otherWorkspace, $otherPage, $otherShop, now()->toDateString());

    $this->post($this->bulkUrl, [
        'ids' => [...$this->todayIds, $foreignId],
        'status' => 'DELIVERED',
    ])->assertRedirect();

    expect(statusOf($foreignId))->toBe('PENDING');
});

test('the page hands the bulk switch state to the frontend', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $url = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_bulk_status_update', false));

    enableBulkStatusUpdate($this->workspace);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_bulk_status_update', true));
});

test('the settings page exposes and saves the bulk status switch', function () {
    $manager = bulkStatusMemberWithPermissions($this->workspace, [PermissionEnum::ManageRmoSettings->value]);

    $this->actingAs($manager)->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('settings.enable_bulk_status_update', false));

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => true,
    ])->assertRedirect(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]));

    expect($this->workspace->fresh()->rmoBulkStatusUpdateEnabled())->toBeTrue();

    $this->actingAs($manager)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
    ])->assertRedirect();

    expect($this->workspace->fresh()->rmoBulkStatusUpdateEnabled())->toBeFalse();
});

test('saving the bulk switch is gated by Manage RMO Settings', function () {
    $outsider = bulkStatusMemberWithPermissions($this->workspace, [PermissionEnum::ViewRmoManagement->value]);

    $this->actingAs($outsider)->put(route('rmo-settings.update', ['workspace' => $this->workspace->slug]), [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => true,
    ])->assertForbidden();

    expect($this->workspace->fresh()->rmoBulkStatusUpdateEnabled())->toBeFalse();
});
