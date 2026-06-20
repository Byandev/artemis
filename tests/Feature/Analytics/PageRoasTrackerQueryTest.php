<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\PageRoasTrackerQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Insight;

test('page roas tracker confirmed totals match dashboard live metrics', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()
        ->forWorkspace($workspace)
        ->forShop($shop)
        ->forOwner($user)
        ->create(['name' => 'Accuracy Page']);

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-06-15 10:00:00',
        'final_amount' => 400,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 3,
        'confirmed_at' => '2026-06-15 18:00:00',
        'final_amount' => 800,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 6,
        'confirmed_at' => '2026-06-15 19:00:00',
        'final_amount' => 999,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-06-14 23:59:59',
        'final_amount' => 999,
    ]);

    // Ad spend is the actual Meta-reported spend (meta_ads_insights.spend),
    // rolled up to the page through its ad set's meta_page_id. Two ad rows on the
    // same day prove the per-page SUM totals 300.
    $adSet = AdSet::create([
        'id' => 7001,
        'meta_ads_account_id' => 9001,
        'meta_ads_campaign_id' => 8001,
        'meta_page_id' => $page->id,
        'name' => 'Accuracy Ad Set',
        'effective_status' => 'ACTIVE',
    ]);

    Insight::create([
        'meta_ads_ad_id' => 5001,
        'meta_ads_set_id' => $adSet->id,
        'meta_ads_account_id' => 9001,
        'date' => '2026-06-15',
        'spend' => 200,
    ]);
    Insight::create([
        'meta_ads_ad_id' => 5002,
        'meta_ads_set_id' => $adSet->id,
        'meta_ads_account_id' => 9001,
        'date' => '2026-06-15',
        'spend' => 100,
    ]);

    $query = [
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
        'statuses' => ['confirmed'],
    ];

    $payload = trackerPayload($workspace, $user, $query);
    $dashboard = $workspace->metrics(
        ['start_date' => '2026-06-15', 'end_date' => '2026-06-15'],
        ['page_ids' => [$page->id]],
    )->extract(['totalOrders', 'totalSales']);

    $cell = $payload['rows'][0]['cells'][(string) $page->id];

    expect($cell['orders'])->toBe($dashboard['totalOrders'])
        ->and($cell['sales'])->toBe($dashboard['totalSales'])
        ->and($cell['orders'])->toBe(2)
        ->and($cell['sales'])->toBe(1200.0)
        ->and($cell['adSpent'])->toBe(300.0)
        ->and($cell['roas'])->toBe(4.0)
        ->and($payload['analysis']['totalOrders'])->toBe(2)
        ->and($payload['analysis']['totalSales'])->toBe(1200.0);
});

test('page roas tracker dedupes operational delivery rows by order per delivery date', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()
        ->forWorkspace($workspace)
        ->forShop($shop)
        ->forOwner($user)
        ->create(['name' => 'Delivery Page']);

    $order = Order::factory()->forPage($page)->create([
        'final_amount' => 750,
        'parcel_status' => 'on_the_way',
    ]);

    DB::table('pancake_order_for_delivery')->insert([
        [
            'order_id' => $order->id,
            'page_id' => $page->id,
            'shop_id' => $shop->id,
            'workspace_id' => $workspace->id,
            'status' => 'PENDING',
            'parcel_status' => 'on_the_way',
            'rider_name' => 'Rider One',
            'rider_phone' => '09170000001',
            'delivery_date' => '2026-06-15',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'order_id' => $order->id,
            'page_id' => $page->id,
            'shop_id' => $shop->id,
            'workspace_id' => $workspace->id,
            'status' => 'PENDING',
            'parcel_status' => 'on_the_way',
            'rider_name' => 'Rider Two',
            'rider_phone' => '09170000002',
            'delivery_date' => '2026-06-15',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $payload = trackerPayload($workspace, $user, [
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
        'statuses' => ['on_delivery'],
    ]);

    $cell = $payload['rows'][0]['cells'][(string) $page->id];

    expect($cell['orders'])->toBe(1)
        ->and($cell['sales'])->toBe(750.0);
});

function trackerPayload(Workspace $workspace, User $user, array $query): array
{
    $request = Request::create('/tracker', 'GET', $query);

    return (new PageRoasTrackerQuery($workspace, $user, $request))->payload();
}
