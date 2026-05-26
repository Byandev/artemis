<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function makeRmoContext(): array
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $page = Page::factory()->forWorkspace($workspace)->create();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $userId = User::factory()->create()->id;

    return ['workspace' => $workspace, 'raw' => $raw, 'page' => $page, 'shop' => $shop, 'userId' => $userId];
}

function seedDelivery(array $ctx, array $overrides = []): int
{
    $order = Order::factory()->forPage($ctx['page'])->create([
        'workspace_id' => $ctx['workspace']->id,
        'shop_id' => $ctx['shop']->id,
    ]);

    return DB::table('pancake_order_for_delivery')->insertGetId(array_merge([
        'order_id' => $order->id,
        'page_id' => $ctx['page']->id,
        'shop_id' => $ctx['shop']->id,
        'workspace_id' => $ctx['workspace']->id,
        'status' => 'In Transit',
        'rider_name' => 'Rider',
        'rider_phone' => '+1',
        'assignee_user_id' => $ctx['userId'],
        'delivery_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

test('assignedOrders returns deliveries for the given user_id and today', function () {
    $ctx = makeRmoContext();
    seedDelivery($ctx);
    seedDelivery($ctx, ['delivery_date' => now()->subDay()->toDateString()]); // not today
    $otherUser = User::factory()->create()->id;
    seedDelivery($ctx, ['assignee_user_id' => $otherUser]); // different user

    $response = $this->getJson("/api/v1/public/rmo-orders?user_id={$ctx['userId']}", [
        'Authorization' => 'Bearer '.$ctx['raw'],
    ])->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('assignedOrders requires user_id and validates integer', function () {
    $ctx = makeRmoContext();

    $this->getJson('/api/v1/public/rmo-orders', ['Authorization' => 'Bearer '.$ctx['raw']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    $this->getJson('/api/v1/public/rmo-orders?user_id=not-an-integer', ['Authorization' => 'Bearer '.$ctx['raw']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('filter[page_id] narrows to specific page (single value)', function () {
    $ctx = makeRmoContext();
    $otherPage = Page::factory()->forWorkspace($ctx['workspace'])->create();

    seedDelivery($ctx); // matches page_id of $ctx['page']
    seedDelivery($ctx, ['page_id' => $otherPage->id]); // different page

    $response = $this->getJson(
        "/api/v1/public/rmo-orders?user_id={$ctx['userId']}&filter[page_id]={$ctx['page']->id}",
        ['Authorization' => 'Bearer '.$ctx['raw']]
    )->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('filter[page_id] accepts comma-separated values', function () {
    $ctx = makeRmoContext();
    $otherPage = Page::factory()->forWorkspace($ctx['workspace'])->create();

    seedDelivery($ctx);
    seedDelivery($ctx, ['page_id' => $otherPage->id]);

    $response = $this->getJson(
        "/api/v1/public/rmo-orders?user_id={$ctx['userId']}&filter[page_id]={$ctx['page']->id},{$otherPage->id}",
        ['Authorization' => 'Bearer '.$ctx['raw']]
    )->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('filter[shop_id] narrows to specific shop', function () {
    $ctx = makeRmoContext();
    $otherShop = Shop::factory()->forWorkspace($ctx['workspace'])->create();

    seedDelivery($ctx);
    seedDelivery($ctx, ['shop_id' => $otherShop->id]);

    $response = $this->getJson(
        "/api/v1/public/rmo-orders?user_id={$ctx['userId']}&filter[shop_id]={$ctx['shop']->id}",
        ['Authorization' => 'Bearer '.$ctx['raw']]
    )->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('filter[status] narrows by status', function () {
    $ctx = makeRmoContext();
    seedDelivery($ctx, ['status' => 'In Transit']);
    seedDelivery($ctx, ['status' => 'Delivered']);

    $response = $this->getJson(
        "/api/v1/public/rmo-orders?user_id={$ctx['userId']}&filter[status]=Delivered",
        ['Authorization' => 'Bearer '.$ctx['raw']]
    )->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('per_page caps results', function () {
    $ctx = makeRmoContext();
    foreach (range(1, 5) as $_) {
        seedDelivery($ctx);
    }

    $response = $this->getJson(
        "/api/v1/public/rmo-orders?user_id={$ctx['userId']}&per_page=2",
        ['Authorization' => 'Bearer '.$ctx['raw']]
    )->assertOk();

    expect($response->json('per_page'))->toBe(2);
    expect($response->json('total'))->toBe(5);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/rmo-orders?user_id=1')->assertStatus(401);
});
