<?php

use App\Models\Order;
use App\Models\Shop;
use App\Models\Workspace;

it('snapshots the shop rts rate by order value over the previous 14 days', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    $inWindow = now()->subDays(3);

    // Delivered: 300 + 300 + 300 = 900. Returned: 400 + 200 = 600.
    // 600 / 1500 = 0.4 by value — but only 2/5 = 0.4 by count too, so the two
    // definitions are deliberately pulled apart in the next test.
    Order::factory()->count(3)->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 3,
        'final_amount' => 300,
        'delivered_at' => $inWindow,
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 400,
        'returning_at' => $inWindow,
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 5,
        'final_amount' => 200,
        'returning_at' => $inWindow,
    ]);

    // Outside the window — must not move the rate.
    Order::factory()->count(10)->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 5000,
        'returning_at' => now()->subDays(40),
    ]);

    // Neither delivered nor returned — excluded by the status filter.
    Order::factory()->count(5)->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 2,
        'final_amount' => 5000,
        'delivered_at' => $inWindow,
    ]);

    $this->artisan('sync:shop-rts-snapshot')->assertSuccessful();

    $shop->refresh();

    expect($shop->rts_snapshot)->toBe(0.4)
        ->and($shop->rts_snapshot_updated_at)->not->toBeNull();
});

it('weights the rate by amount rather than by order count', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    $inWindow = now()->subDays(3);

    // 3 delivered at 100 = 300, 1 returned at 900.
    // By count that would be 1/4 = 0.25; by value it is 900/1200 = 0.75.
    Order::factory()->count(3)->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 3,
        'final_amount' => 100,
        'delivered_at' => $inWindow,
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 900,
        'returning_at' => $inWindow,
    ]);

    $this->artisan('sync:shop-rts-snapshot')->assertSuccessful();

    expect($shop->refresh()->rts_snapshot)->toBe(0.75);
});

it('leaves the snapshot null for a shop with no delivered or returned value in the window', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create(['rts_snapshot' => 0.9]);

    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 500,
        'returning_at' => now()->subDays(40),
    ]);

    $this->artisan('sync:shop-rts-snapshot')->assertSuccessful();

    // Null, not 0 — "no value to rate" is not the same as "0% RTS".
    expect($shop->refresh()->rts_snapshot)->toBeNull();
});

it('leaves the snapshot null when every order in the window is zero-amount', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create(['rts_snapshot' => 0.9]);

    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 3,
        'final_amount' => 0,
        'delivered_at' => now()->subDays(3),
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 0,
        'returning_at' => now()->subDays(3),
    ]);

    // Dividing by a zero denominator has no honest answer — null, not 0%.
    $this->artisan('sync:shop-rts-snapshot')->assertSuccessful();

    expect($shop->refresh()->rts_snapshot)->toBeNull();
});

it('honours a custom lookback window', function () {
    $workspace = Workspace::factory()->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    // 20 days back: outside the default 14-day window, inside a 30-day one.
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 3,
        'final_amount' => 500,
        'delivered_at' => now()->subDays(20),
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'status' => 4,
        'final_amount' => 500,
        'returning_at' => now()->subDays(20),
    ]);

    $this->artisan('sync:shop-rts-snapshot')->assertSuccessful();
    expect($shop->refresh()->rts_snapshot)->toBeNull();

    $this->artisan('sync:shop-rts-snapshot', ['--days' => 30])->assertSuccessful();
    expect($shop->refresh()->rts_snapshot)->toBe(0.5);
});
