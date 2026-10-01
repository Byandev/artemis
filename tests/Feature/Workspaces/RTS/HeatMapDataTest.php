<?php

use App\Models\Page;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;

/**
 * The endpoint the heat map fetches. It reads the daily rollup and hands back one
 * entry per shaded area, each naming the GADM polygons it covers, so the front end
 * does no name matching of its own.
 */
function heatMapOrder(
    Workspace $workspace,
    Page $page,
    int $status,
    string $date,
    string $province,
    float $amount = 1000,
): void {
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
}

function heatMapFetch(Workspace $workspace, string $groupBy = 'province'): array
{
    return test()->getJson(sprintf(
        '/workspaces/%s/rts/heat-map/data?start_date=2026-03-01&end_date=2026-03-31&group_by=%s',
        $workspace->slug,
        $groupBy,
    ))->assertOk()->json();
}

beforeEach(function () {
    ['user' => $this->user, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->page = Page::factory()->create([
        'workspace_id' => $this->workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($this->workspace)->create()->id,
    ]);

    $this->actingAs($this->user);
});

it('returns one entry per province, naming its polygon', function () {
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra', 800);
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-10', 'Abra', 200);

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['gids'])->toBe(['PHL.1_1'])
        ->and($data['rows'][0]['province_name'])->toBe('Abra')
        ->and($data['rows'][0]['province_id'])->toBe('63_598')
        ->and($data['rows'][0]['orders'])->toBe(2)
        ->and($data['rows'][0]['returning_count'])->toBe(1);
});

it('rates an area by value returned, not by orders returned', function () {
    // One cheap delivery and one expensive return: half the orders came back, but
    // four fifths of the money did.
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra', 200);
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-10', 'Abra', 800);

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'][0]['rts_rate_percentage'])->toEqual(80)
        ->and($data['totals']['rts_rate_percentage'])->toEqual(80)
        ->and($data['totals']['sales'])->toEqual(1000)
        ->and($data['totals']['returning_amount'])->toEqual(800);
});

it('groups into island groups, shading every province in one', function () {
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-10', 'Abra');       // North Luzon
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Cagayan');    // North Luzon
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Cebu');       // Visayas

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace, 'region');
    $regions = collect($data['rows'])->keyBy('region');

    expect($data['rows'])->toHaveCount(2)
        ->and($regions['North Luzon']['orders'])->toBe(2)
        ->and($regions['Visayas']['orders'])->toBe(1)
        // A region has no polygon of its own, so it shades all of its provinces.
        ->and($regions['North Luzon']['gids'])->toContain('PHL.1_1')
        ->and(count($regions['North Luzon']['gids']))->toBeGreaterThan(1);
});

it('names a polygon shared by two provinces after both', function () {
    // Davao Occidental was split from Davao del Sur after GADM drew its boundaries,
    // so one shape carries both and must not credit either alone.
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Davao-del-sur');
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-10', 'Davao-occidental');

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['province_name'])->toBe('Davao del sur / Davao occidental')
        ->and($data['rows'][0]['orders'])->toBe(2);
});

it('labels provinces from Pancake own list rather than title casing', function () {
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Davao-del-norte');

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    expect(heatMapFetch($this->workspace)['rows'][0]['province_name'])
        ->toBe('Davao del norte');
});

it('reports areas it could not place separately from the shaded ones', function () {
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-10', 'Nowhere At All');
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra');

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['unmapped'])->toHaveCount(1)
        // Totals still cover every order, mapped or not.
        ->and($data['totals']['orders'])->toBe(2)
        ->and($data['totals']['unmapped_areas'])->toBe(1);
});

it('honours the date range', function () {
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra');
    heatMapOrder($this->workspace, $this->page, 3, '2026-04-10', 'Cebu');

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-04-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['province_name'])->toBe('Abra');
});

it('does not leak another workspace rows', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $otherPage = Page::factory()->create([
        'workspace_id' => $other->id,
        'shop_id' => Shop::factory()->forWorkspace($other)->create()->id,
    ]);

    heatMapOrder($other, $otherPage, 4, '2026-03-10', 'Cebu');
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra');

    $this->artisan('build-order-location-daily-records', ['--date' => '2026-03-10']);

    $data = heatMapFetch($this->workspace);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['province_name'])->toBe('Abra');
});

it('reports the span it has data for, so an empty map can explain itself', function () {
    heatMapOrder($this->workspace, $this->page, 3, '2026-03-10', 'Abra');
    heatMapOrder($this->workspace, $this->page, 4, '2026-03-20', 'Cebu');

    $this->artisan('build-order-location-daily-records', ['--from' => '2026-03-10', '--to' => '2026-03-20']);

    // A range with nothing in it still says what the workspace does have.
    $empty = test()->getJson(sprintf(
        '/workspaces/%s/rts/heat-map/data?start_date=2026-07-01&end_date=2026-07-31&group_by=province',
        $this->workspace->slug,
    ))->assertOk()->json();

    expect($empty['rows'])->toBeEmpty()
        ->and($empty['available']['first'])->toBe('2026-03-10')
        ->and($empty['available']['last'])->toBe('2026-03-20');
});

it('reports no span at all for a workspace with no orders', function () {
    ['user' => $user, 'workspace' => $empty] = makeWorkspaceWithOwner();

    $data = $this->actingAs($user)->getJson(sprintf(
        '/workspaces/%s/rts/heat-map/data?start_date=2026-03-01&end_date=2026-03-31&group_by=province',
        $empty->slug,
    ))->assertOk()->json();

    expect($data['rows'])->toBeEmpty()
        ->and($data['available']['first'])->toBeNull()
        ->and($data['available']['last'])->toBeNull();
});

it('renders the page', function () {
    $this->get("/workspaces/{$this->workspace->slug}/rts/heat-map")
        ->assertOk();
});
