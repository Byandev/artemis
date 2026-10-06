<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Order;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDefaultStatuses;
use Database\Seeders\RmoStatusSeeder;
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

test('a new workspace starts with the default cx and rider statuses', function () {
    expect($this->workspace->rmoCxStatuses()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RmoDefaultStatuses::CX)->sort()->values()->all())
        ->and($this->workspace->rmoRiderStatuses()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RmoDefaultStatuses::RIDER)->sort()->values()->all());
});

test('the seeder backfills missing defaults without duplicating or restoring renamed ones', function () {
    $this->workspace->rmoCxStatuses()->where('name', 'CX CBR')->delete();
    $this->workspace->rmoRiderStatuses()->where('name', 'DELIVERED')->update(['name' => 'Delivered OK']);

    $this->seed(RmoStatusSeeder::class);
    $this->seed(RmoStatusSeeder::class);

    expect($this->workspace->rmoCxStatuses()->count())->toBe(count(RmoDefaultStatuses::CX))
        ->and($this->workspace->rmoRiderStatuses()->where('name', 'DELIVERED')->count())->toBe(1)
        ->and($this->workspace->rmoRiderStatuses()->where('name', 'Delivered OK')->count())->toBe(1);
});

test('the RMO statuses settings page lists both status types', function () {
    // Wipe the defaults so the two below are the only ones listed.
    OrderForDeliveryCxStatus::where('workspace_id', $this->workspace->id)->delete();
    OrderForDeliveryRiderStatus::where('workspace_id', $this->workspace->id)->delete();
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

    $cx = OrderForDeliveryCxStatus::where('workspace_id', $this->workspace->id)->where('name', 'No Answer')->sole();
    expect(OrderForDeliveryRiderStatus::where('workspace_id', $this->workspace->id)->where('name', 'Out of Area')->exists())->toBeTrue();

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

test('the public RMO page sets and clears cx and rider statuses, keeping the main status', function () {
    $cx = OrderForDeliveryCxStatus::create(['workspace_id' => $this->workspace->id, 'name' => 'No Answer']);
    $rider = OrderForDeliveryRiderStatus::create(['workspace_id' => $this->workspace->id, 'name' => 'Out of Area']);
    $id = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());
    $url = "/public/workspaces/{$this->workspace->slug}/rts/rmo-management/{$id}/sub-status";

    $this->post($url, ['type' => 'cx', 'status_id' => $cx->id])->assertSessionHas('success');
    $this->post($url, ['type' => 'rider', 'status_id' => $rider->id])->assertSessionHas('success');

    $row = DB::table('pancake_order_for_delivery')->find($id);
    expect($row->cx_status_id)->toBe($cx->id)
        ->and($row->rider_status_id)->toBe($rider->id)
        ->and($row->status)->toBe('PENDING');

    $this->post($url, ['type' => 'cx', 'status_id' => null]);
    expect(DB::table('pancake_order_for_delivery')->find($id)->cx_status_id)->toBeNull();
});

test('the public RMO page rejects another workspace\'s status and closed dates', function () {
    $other = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $foreign = $other->rmoCxStatuses()->first();
    $mine = $this->workspace->rmoCxStatuses()->first();

    $today = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());
    $this->post("/public/workspaces/{$this->workspace->slug}/rts/rmo-management/{$today}/sub-status", [
        'type' => 'cx', 'status_id' => $foreign->id,
    ])->assertSessionHas('error');
    expect(DB::table('pancake_order_for_delivery')->find($today)->cx_status_id)->toBeNull();

    $old = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->subDays(3)->toDateString());
    $this->post("/public/workspaces/{$this->workspace->slug}/rts/rmo-management/{$old}/sub-status", [
        'type' => 'cx', 'status_id' => $mine->id,
    ])->assertSessionHas('error');
    expect(DB::table('pancake_order_for_delivery')->find($old)->cx_status_id)->toBeNull();
});

test('the public RMO page lists the statuses and filters by them, with none for untagged rows', function () {
    $cx = $this->workspace->rmoCxStatuses()->where('name', 'CX CBR')->first();
    $rider = $this->workspace->rmoRiderStatuses()->where('name', 'RIDER OTW')->first();
    $date = today()->toDateString();

    $tagged = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, $date);
    $riderOnly = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, $date);
    $untagged = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, $date);
    DB::table('pancake_order_for_delivery')->where('id', $tagged)->update(['cx_status_id' => $cx->id]);
    DB::table('pancake_order_for_delivery')->where('id', $riderOnly)->update(['rider_status_id' => $rider->id]);

    // A super admin skips the public-pages password gate.
    $admin = User::factory()->create(['is_super_admin' => true]);
    $get = fn (array $filter = []) => $this->withoutVite()
        ->actingAs($admin)
        ->get(route('public-page.rmo-management', ['workspace' => $this->workspace->slug, 'filter' => $filter]))
        ->assertOk();
    $ids = fn (array $filter) => collect($get($filter)->viewData('page')['props']['orders']['data'])
        ->pluck('id')->sort()->values()->all();

    $get()->assertInertia(fn ($page) => $page
        ->has('cx_statuses', count(RmoDefaultStatuses::CX))
        ->has('rider_statuses', count(RmoDefaultStatuses::RIDER)));

    expect($ids(['cx_status_id' => (string) $cx->id]))->toBe([$tagged])
        ->and($ids(['cx_status_id' => 'none']))->toBe([$riderOnly, $untagged])
        ->and($ids(['rider_status_id' => (string) $rider->id]))->toBe([$riderOnly]);
});

test('the public RMO page bulk-sets and clears a cx or rider status', function () {
    $this->workspace->rmoSetting()->updateOrCreate(
        ['workspace_id' => $this->workspace->id],
        ['enable_bulk_status_update' => true],
    );
    $rider = $this->workspace->rmoRiderStatuses()->where('name', 'RIDER OTW')->first();
    $url = "/public/workspaces/{$this->workspace->slug}/rts/rmo-management/bulk-sub-status";

    $a = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());
    $b = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());
    $closed = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->subDays(3)->toDateString());

    $this->post($url, ['ids' => [$a, $b, $closed], 'type' => 'rider', 'status_id' => $rider->id])
        ->assertSessionHas('success');

    $riderOf = fn (int $id) => DB::table('pancake_order_for_delivery')->find($id)->rider_status_id;
    expect($riderOf($a))->toBe($rider->id)
        ->and($riderOf($b))->toBe($rider->id)
        ->and($riderOf($closed))->toBeNull();

    $this->post($url, ['ids' => [$a], 'type' => 'rider', 'status_id' => null])->assertSessionHas('success');
    expect($riderOf($a))->toBeNull();
});

test('bulk cx / rider status is refused while bulk status update is off', function () {
    $cx = $this->workspace->rmoCxStatuses()->first();
    $id = seedSubStatusDelivery($this->workspace, $this->page, $this->shop, today()->toDateString());

    $this->post("/public/workspaces/{$this->workspace->slug}/rts/rmo-management/bulk-sub-status", [
        'ids' => [$id], 'type' => 'cx', 'status_id' => $cx->id,
    ])->assertSessionHas('error');

    expect(DB::table('pancake_order_for_delivery')->find($id)->cx_status_id)->toBeNull();
});
