<?php

use App\Models\Page;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\PageOrdersToLocationDailyRecord;

/**
 * The rollup the RTS heat map reads. An order lands on the day it reached its
 * outcome — delivered_at when delivered, returning_at when returned — and carries
 * the province id, island group and map polygon resolved at build time.
 */
function locationOrder(
    Workspace $workspace,
    Page $page,
    int $status,
    string $date,
    string $province,
    float $amount = 1000,
): Order {
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
        'page_id' => $page->id,
        'status' => $status,
        'status_name' => $status === 3 ? 'delivered' : 'returned',
        'order_number' => (string) fake()->unique()->numberBetween(100000, 999999),
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => $date.' 09:00:00',
        'final_amount' => $amount,
        $status === 3 ? 'delivered_at' : 'returning_at' => $date.' 15:00:00',
    ]);

    ShippingAddress::create([
        'order_id' => $order->id,
        'province_name' => $province,
    ]);

    return $order;
}

function locationPage(Workspace $workspace): Page
{
    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($workspace)->create()->id,
    ]);
}

it('rolls delivered and returned orders into one row per province per day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    locationOrder($workspace, $page, 3, '2026-03-10', 'Abra', 1000);
    locationOrder($workspace, $page, 3, '2026-03-10', 'Abra', 500);
    locationOrder($workspace, $page, 4, '2026-03-10', 'Abra', 300);

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10'])
        ->assertSuccessful();

    $row = PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->orders)->toBe(3)
        ->and((float) $row->sales)->toBe(1800.0)
        ->and($row->delivered_count)->toBe(2)
        ->and((float) $row->delivered_amount)->toBe(1500.0)
        ->and($row->returning_count)->toBe(1)
        ->and((float) $row->returning_amount)->toBe(300.0)
        ->and($row->gadm_province_gid)->toBe('PHL.1_1')
        ->and($row->page_id)->toBe($page->id);
});

it('stamps the province id and island group from Pancake own list', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    locationOrder($workspace, $page, 3, '2026-03-10', 'Davao-del-sur');
    locationOrder($workspace, $page, 4, '2026-03-10', 'Metro-manila');

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $rows = PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)
        ->get()
        ->keyBy('province_name');

    expect($rows['Davao-del-sur']->province_id)->toBe('63_738')
        ->and($rows['Davao-del-sur']->region)->toBe('Mindanao')
        ->and($rows['Metro-manila']->province_id)->toBe('63_219')
        ->and($rows['Metro-manila']->region)->toBe('Metro Manila');
});

it('buckets an order by the day it reached its outcome, not the day it was placed', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    // Placed on the 10th (inserted_at), returned on the 12th.
    locationOrder($workspace, $page, 4, '2026-03-12', 'Abra')
        ->update(['inserted_at' => '2026-03-10 09:00:00']);

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-12'])
        ->assertSuccessful();

    $rows = PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->date->toDateString())->toBe('2026-03-12');
});

it('rebuilds a day wholesale so re-running does not double count', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    locationOrder($workspace, $page, 3, '2026-03-10', 'Abra');

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-10']);
    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-10']);

    $row = PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->orders)->toBe(1);
});

it('keeps an unresolvable destination but leaves its map ids null', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    locationOrder($workspace, $page, 4, '2026-03-10', 'Nowhere At All');

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-10']);

    $row = PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)->sole();

    // The orders still count towards the workspace totals — they just cannot be shaded.
    expect($row->returning_count)->toBe(1)
        ->and($row->gadm_province_gid)->toBeNull()
        ->and($row->province_id)->toBeNull()
        ->and($row->region)->toBeNull();
});

it('ignores orders that never reached a delivered or returned status', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = locationPage($workspace);

    Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
        'page_id' => $page->id,
        'status' => 1,
        'status_name' => 'confirmed',
        'order_number' => (string) fake()->unique()->numberBetween(100000, 999999),
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => '2026-03-10 09:00:00',
    ]);

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-10']);

    expect(PageOrdersToLocationDailyRecord::where('workspace_id', $workspace->id)->count())->toBe(0);
});
