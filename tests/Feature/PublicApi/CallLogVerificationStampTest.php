<?php

use App\Jobs\BackfillCallLogPersonasForDay;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Support\CallLogPersona;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * A synced call no delivery accounts for is still stamped where it can be.
 *
 * The number was never on a delivery loaded that day, but it is the phone on an
 * order the workspace confirmed that day — a CSR verifying the order, hours
 * before it is ever loaded for delivery. The sync stamps that itself now rather
 * than leaving the row null for the backfill.
 */
const VERIFY_DATE = '2026-07-20';

function verifyOrder($workspace, string $phone, string $confirmedAt): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create(['confirmed_at' => $confirmedAt]);

    ShippingAddress::factory()->create(['order_id' => $order->id, 'phone_number' => $phone]);

    return $order;
}

function syncCall($workspace, string $raw, string $phone, string $timestamp)
{
    return test()->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => $phone,
            'type' => 'outgoing',
            'duration' => 30,
            'timestamp' => $timestamp,
        ]],
    ], ['Authorization' => 'Bearer '.$raw]);
}

test('a call to a number on an order confirmed that day syncs as verification', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $order = verifyOrder($workspace, '09170000001', VERIFY_DATE.' 09:00:00');

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id)
        // Nothing was out for delivery, so there is no delivery row to point at.
        ->and($call->order_for_delivery_id)->toBeNull();
});

test('the hour either happened at makes no difference', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Called first thing, confirmed last thing — same calendar day is the rule.
    $order = verifyOrder($workspace, '09170000001', VERIFY_DATE.' 23:45:00');

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T07:05:00+08:00')->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id);
});

test('the number is matched however either side spells it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Pancake keeps this one international; the handset reported it local.
    $order = verifyOrder($workspace, '+639170000001', VERIFY_DATE.' 09:00:00');

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    expect((int) CallLog::where('phone_number', '09170000001')->firstOrFail()->order_id)->toBe($order->id);
});

test('an order confirmed on another day leaves the call unstamped', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    verifyOrder($workspace, '09170000001', '2026-07-19 09:00:00');

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();

    expect($call->persona)->toBeNull()
        ->and($call->order_id)->toBeNull();
});

test('an order confirmed in another workspace is not a match', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    verifyOrder($other, '09170000001', VERIFY_DATE.' 09:00:00');

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    expect(CallLog::where('phone_number', '09170000001')->firstOrFail()->persona)->toBeNull();
});

test('a delivery loaded that day still outranks the confirmation behind it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Confirmed and dispatched the same day: the call is about the delivery.
    $order = verifyOrder($workspace, '09170000001', VERIFY_DATE.' 09:00:00');

    $delivery = OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'delivery_date' => VERIFY_DATE,
    ]);

    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::CUSTOMER)
        ->and((int) $call->order_for_delivery_id)->toBe($delivery->id);
});

test('the backfill takes a verification stamp back when the delivery lands late', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $order = verifyOrder($workspace, '09170000001', VERIFY_DATE.' 09:00:00');

    // Synced before the day's deliveries were pulled in, so verification is all
    // the sync had to go on.
    syncCall($workspace, $raw, '09170000001', VERIFY_DATE.'T10:15:00+08:00')->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();
    expect($call->persona)->toBe(CallLogPersona::VERIFICATION);

    $delivery = OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'delivery_date' => VERIFY_DATE,
    ]);

    (new BackfillCallLogPersonasForDay($workspace->id, VERIFY_DATE))->handle();

    $call->refresh();

    expect($call->persona)->toBe(CallLogPersona::CUSTOMER)
        ->and((int) $call->order_for_delivery_id)->toBe($delivery->id);
});
