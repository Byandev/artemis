<?php

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function scanReturnSetup(array $shopAttrs = []): array
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $shop = Shop::factory()->forWorkspace($workspace)->create(['pos_token' => 'pos-token', ...$shopAttrs]);
    $order = Order::factory()->forWorkspace($workspace)->create([
        'shop_id' => $shop->id,
        'order_number' => '1234',
        'tracking_code' => 'TRK-1',
        'status' => 2,
        'status_name' => 'shipped',
        'parcel_status' => 'On Delivery',
    ]);

    return compact('workspace', 'raw', 'shop', 'order');
}

function scanReturn(string $raw, array $body)
{
    return test()->postJson('/api/v1/public/shops/scan-return', $body, ['Authorization' => 'Bearer '.$raw]);
}

test('marks the order as returned in Pancake POS and locally', function () {
    ['raw' => $raw, 'shop' => $shop, 'order' => $order] = scanReturnSetup();
    Http::fake(['pos.pages.fm/*' => Http::response(['success' => true])]);

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])
        ->assertOk()
        ->assertJsonPath('message', 'Order marked as returned.')
        ->assertJsonPath('order.id', $order->id)
        ->assertJsonPath('order.order_number', '1234')
        ->assertJsonPath('order.tracking_code', 'TRK-1')
        ->assertJsonPath('order.parcel_status', 'returned');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_starts_with($request->url(), "https://pos.pages.fm/api/v1/shops/{$shop->id}/orders/1234?")
        && str_contains($request->url(), 'api_key=pos-token')
        && $request['status'] === 5);

    $order->refresh();
    expect($order->status)->toBe(5)
        ->and($order->status_name)->toBe('returned')
        ->and($order->parcel_status)->toBe('returned')
        ->and($order->returned_at)->not->toBeNull();
});

test('leaves the order untouched when Pancake POS errors', function () {
    ['raw' => $raw, 'shop' => $shop, 'order' => $order] = scanReturnSetup();
    Http::fake(['pos.pages.fm/*' => Http::response(['message' => 'boom'], 500)]);

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])->assertStatus(502);

    expect($order->refresh()->parcel_status)->toBe('On Delivery');
});

test('leaves the order untouched when Pancake POS answers success false', function () {
    ['raw' => $raw, 'shop' => $shop, 'order' => $order] = scanReturnSetup();
    Http::fake(['pos.pages.fm/*' => Http::response(['success' => false, 'message' => 'Invalid status'])]);

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])
        ->assertStatus(502)
        ->assertJsonPath('pancake_message', 'Invalid status');

    expect($order->refresh()->parcel_status)->toBe('On Delivery');
});

test('rejects a shop without a POS token', function () {
    ['raw' => $raw, 'shop' => $shop] = scanReturnSetup(['pos_token' => null]);
    Http::fake();

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])->assertStatus(422);

    Http::assertNothingSent();
});

test('rejects an order already marked as returned', function () {
    ['raw' => $raw, 'shop' => $shop, 'order' => $order] = scanReturnSetup();
    $order->update(['parcel_status' => 'returned']);
    Http::fake();

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])->assertStatus(422);

    Http::assertNothingSent();
});

test('returns 404 for an unknown tracking code', function () {
    ['raw' => $raw, 'shop' => $shop] = scanReturnSetup();
    Http::fake();

    scanReturn($raw, ['shop_id' => $shop->id, 'tracking_code' => 'NOPE'])->assertNotFound();

    Http::assertNothingSent();
});

test('returns 404 for an order in another workspace', function () {
    ['shop' => $shop] = scanReturnSetup();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    ['raw' => $otherRaw] = makeApiKey($other);
    Http::fake();

    scanReturn($otherRaw, ['shop_id' => $shop->id, 'tracking_code' => 'TRK-1'])->assertNotFound();

    Http::assertNothingSent();
});

test('validates the request body', function () {
    ['raw' => $raw] = scanReturnSetup();

    scanReturn($raw, [])->assertStatus(422)->assertJsonValidationErrors(['shop_id', 'tracking_code']);
});

test('rejects unauthenticated request', function () {
    $this->postJson('/api/v1/public/shops/scan-return', [])->assertStatus(401);
});
