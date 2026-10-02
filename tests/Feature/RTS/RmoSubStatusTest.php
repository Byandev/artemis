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
use Modules\Pancake\Models\OrderForDeliveryCxStatus;
use Modules\Pancake\Models\OrderForDeliveryRiderStatus;

/** A workspace member whose role carries "Manage RMO Settings". */
function subStatusSettingsManager(Workspace $workspace): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    $permission = Permission::firstOrCreate(
        ['name' => PermissionEnum::ManageRmoSettings->value],
        ['category' => 'RTS'],
    );
    DB::table('role_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

function seedSubStatusDelivery(Workspace $workspace, Page $page, Shop $shop, string $deliveryDate): int
{
    $order = Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
    ]);

    return DB::table('pancake_order_for_delivery')->insertGetId([
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
    ]);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    subscribeWorkspace($this->workspace);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();
    $this->manager = subStatusSettingsManager($this->workspace);
    $this->statusesUrl = fn (string $type) => "/workspaces/{$this->workspace->slug}/settings/rmo/statuses/{$type}";
});

test('the RMO statuses settings page lists both status types', function () {
    OrderForDeliveryCxStatus::create(['workspace_id' => $this->workspace->id, 'name' => 'Busy']);
    OrderForDeliveryRiderStatus::create(['workspace_id' => $this->workspace->id, 'name' => 'Out of Area']);

    $this->withoutVite()
        ->actingAs($this->manager)
        ->get("/workspaces/{$this->workspace->slug}/settings/rmo/statuses")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/rmo-statuses')
            ->where('cxStatuses.0.name', 'Busy')
            ->where('riderStatuses.0.name', 'Out of Area'));
});

test('a settings manager can create, rename and delete cx and rider statuses', function () {
    $this->actingAs($this->manager)
        ->post(($this->statusesUrl)('cx'), ['name' => ' No Answer '])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->manager)
        ->post(($this->statusesUrl)('rider'), ['name' => 'Out of Area'])
        ->assertSessionHasNoErrors();

    $cx = OrderForDeliveryCxStatus::where('workspace_id', $this->workspace->id)->sole();
    expect($cx->name)->toBe('No Answer');
    expect(OrderForDeliveryRiderStatus::where('workspace_id', $this->workspace->id)->count())->toBe(1);

    $this->actingAs($this->manager)
        ->put(($this->statusesUrl)('cx')."/{$cx->id}", ['name' => 'Unreachable'])
        ->assertSessionHasNoErrors();
    expect($cx->fresh()->name)->toBe('Unreachable');

    $id = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());
    DB::table('pancake_order_for_delivery')->where('id', $id)->update(['cx_status_id' => $cx->id]);

    $this->actingAs($this->manager)->delete(($this->statusesUrl)('cx')."/{$cx->id}");

    expect(OrderForDeliveryCxStatus::find($cx->id))->toBeNull();
    expect(DB::table('pancake_order_for_delivery')->find($id)->cx_status_id)->toBeNull();
});

test('status names are unique per workspace and type', function () {
    OrderForDeliveryCxStatus::create(['workspace_id' => $this->workspace->id, 'name' => 'Busy']);

    $this->actingAs($this->manager)
        ->post(($this->statusesUrl)('cx'), ['name' => 'Busy'])
        ->assertSessionHasErrors('name');

    // The same name is fine on the other list.
    $this->actingAs($this->manager)
        ->post(($this->statusesUrl)('rider'), ['name' => 'Busy'])
        ->assertSessionHasNoErrors();
});

test('statuses from another workspace cannot be edited', function () {
    $other = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $foreign = OrderForDeliveryCxStatus::create(['workspace_id' => $other->id, 'name' => 'Foreign']);

    $this->actingAs($this->manager)
        ->put(($this->statusesUrl)('cx')."/{$foreign->id}", ['name' => 'Hijacked'])
        ->assertNotFound();
});

test('a user without the permission cannot manage statuses', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(($this->statusesUrl)('cx'), ['name' => 'Nope'])
        ->assertForbidden();
});
