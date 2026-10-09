<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function totalSalesForShops(Workspace $workspace, array $shopIds): float
{
    return $workspace->metrics(
        ['start_date' => '2026-04-01', 'end_date' => '2026-04-30'],
        ['shop_ids' => implode(',', $shopIds)],
    )->extract(['totalSales'])['totalSales'];
}

test('shop filter counts orders by their own shop_id', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()->forShop($shop)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 500,
    ]);

    expect(totalSalesForShops($workspace, [$shop->id]))->toBe(500.0);
});

test('shop filter keeps page-less (Webcake) orders', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'page_id' => null,
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 300,
    ]);

    expect(totalSalesForShops($workspace, [$shop->id]))->toBe(300.0);
});

test('shop filter keeps orders whose page was re-synced into another shop', function () {
    $workspace = Workspace::factory()->create();
    $shopA = Shop::factory()->forWorkspace($workspace)->create();
    $shopB = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()->forShop($shopB)->create();

    Order::factory()->forPage($page)->create([
        'shop_id' => $shopA->id,
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 200,
    ]);

    expect(totalSalesForShops($workspace, [$shopA->id]))->toBe(200.0)
        ->and(totalSalesForShops($workspace, [$shopB->id]))->toBe(0.0);
});

test('page-less orders show up under their shop in the per-shop breakdown', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'page_id' => null,
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 300,
    ]);

    $metrics = $workspace->metrics(
        ['start_date' => '2026-04-01', 'end_date' => '2026-04-30'],
        ['shop_ids' => (string) $shop->id],
    );

    foreach (['totalSales' => 300.0, 'totalOrders' => 1.0, 'aov' => 300.0, 'uniqueCustomerCount' => 1.0] as $name => $expected) {
        $rows = collect($metrics->perShop($name));

        expect($rows)->toHaveCount(1, $name)
            ->and((int) $rows->first()->shop_id)->toBe($shop->id)
            ->and((float) $rows->first()->value)->toBe($expected);
    }
});

test('every dashboard metric runs with a shop filter, alone and with page/user filters', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()->forShop($shop)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
    ]);

    $filters = [
        ['shop_ids' => (string) $shop->id],
        ['shop_ids' => (string) $shop->id, 'page_ids' => (string) $page->id],
        ['shop_ids' => (string) $shop->id, 'user_ids' => (string) $page->owner_id],
    ];

    foreach ($filters as $filter) {
        $metrics = $workspace->metrics(['start_date' => '2026-04-01', 'end_date' => '2026-04-30'], $filter);

        foreach ($metrics->keys() as $name) {
            $metrics->extract([$name]);

            foreach (['perPage', 'perShop', 'perUser'] as $method) {
                try {
                    $metrics->{$method}($name);
                } catch (InvalidArgumentException) {
                    // Metric doesn't support this breakdown.
                }
            }
        }
    }
})->throwsNoExceptions();
