<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Support\CallLogPersona;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * A synced call is stamped with the delivery it matched, not just the order.
 *
 * The same order can be loaded for delivery on more than one day, so order_id
 * alone does not say which attempt the call belongs to — order_for_delivery_id
 * does.
 */
function stampDelivery($workspace, string $date, string $customerPhone, string $riderPhone): OrderForDelivery
{
    $order = Order::factory()->forWorkspace($workspace)->create();

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => $customerPhone,
        'rider_name' => 'Rider',
        'rider_phone' => $riderPhone,
        'delivery_date' => $date,
    ]);
}

test('sync stamps the matched delivery id alongside the order id and persona', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $delivery = stampDelivery($workspace, '2026-07-20', '09170000001', '09180000001');

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [
            [
                'phone_number' => '09170000001',
                'type' => 'outgoing',
                'duration' => 45,
                'timestamp' => '2026-07-20T10:15:00+08:00',
            ],
            [
                'phone_number' => '09180000001',
                'type' => 'outgoing',
                'duration' => 20,
                'timestamp' => '2026-07-20T10:20:00+08:00',
            ],
            [
                // On no delivery that day — stays unstamped.
                'phone_number' => '09990000009',
                'type' => 'outgoing',
                'duration' => 5,
                'timestamp' => '2026-07-20T10:25:00+08:00',
            ],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $customerCall = CallLog::where('phone_number', '09170000001')->firstOrFail();
    expect($customerCall->persona)->toBe(CallLogPersona::CUSTOMER)
        ->and((int) $customerCall->order_id)->toBe((int) $delivery->order_id)
        ->and((int) $customerCall->order_for_delivery_id)->toBe($delivery->id);

    $riderCall = CallLog::where('phone_number', '09180000001')->firstOrFail();
    expect($riderCall->persona)->toBe(CallLogPersona::RIDER)
        ->and((int) $riderCall->order_for_delivery_id)->toBe($delivery->id);

    $unmatched = CallLog::where('phone_number', '09990000009')->firstOrFail();
    expect($unmatched->persona)->toBeNull()
        ->and($unmatched->order_id)->toBeNull()
        ->and($unmatched->order_for_delivery_id)->toBeNull();

    expect($customerCall->orderForDelivery->is($delivery))->toBeTrue();
});

test('a redelivery on a later day stamps its own delivery row', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $first = stampDelivery($workspace, '2026-07-20', '09170000001', '09180000001');

    // Same order, loaded again the next day: same order_id, new delivery row.
    $second = OrderForDelivery::create([
        ...$first->only([
            'order_id', 'page_id', 'shop_id', 'workspace_id', 'status', 'parcel_status',
            'customer_name', 'customer_phone', 'rider_name', 'rider_phone',
        ]),
        'delivery_date' => '2026-07-21',
    ]);

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => '09170000001',
            'type' => 'outgoing',
            'duration' => 45,
            'timestamp' => '2026-07-21T09:00:00+08:00',
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $call = CallLog::where('phone_number', '09170000001')->firstOrFail();

    expect((int) $call->order_for_delivery_id)->toBe($second->id)
        ->and((int) $call->order_id)->toBe((int) $first->order_id);
});

/**
 * An order on one or both of the day's stamps, reachable on the number given.
 *
 * The verification rule reads the shipping address rather than the delivery —
 * these orders have no delivery row yet, which is the whole point of the call.
 * $insertedAt is the day it came in; left out, the factory stamps it now, which
 * is a day of its own and not the one under test.
 */
function confirmedOrderFor($workspace, string $phone, ?string $confirmedAt, ?string $insertedAt = null): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create(array_filter([
        'confirmed_at' => $confirmedAt,
        'inserted_at' => $insertedAt,
    ]));

    ShippingAddress::factory()->create(['order_id' => $order->id, 'phone_number' => $phone]);

    return $order;
}

test('a number on no delivery is stamped verification when the order was confirmed that day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $order = confirmedOrderFor($workspace, '09170000002', '2026-07-20 08:00:00');

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => '09170000002',
            'type' => 'outgoing',
            'duration' => 60,
            'timestamp' => '2026-07-20T10:15:00+08:00',
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $call = CallLog::where('phone_number', '09170000002')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id)
        // Verification is a call before the parcel is ever loaded, so there is
        // no delivery to point at.
        ->and($call->order_for_delivery_id)->toBeNull();
});

test('a number on no delivery is stamped verification when the order came in that day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Rung the day it landed and not confirmed at the time — commonly not
    // confirmed at all, which on confirmed_at alone leaves the call matched to
    // nothing and counted nowhere.
    $order = confirmedOrderFor($workspace, '09170000005', null, '2026-07-20 07:30:00');

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => '09170000005',
            'type' => 'outgoing',
            'duration' => 60,
            'timestamp' => '2026-07-20T10:15:00+08:00',
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $call = CallLog::where('phone_number', '09170000005')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id)
        ->and($call->order_for_delivery_id)->toBeNull();
});

test('an order neither taken in nor confirmed that day is not a verification match', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    confirmedOrderFor($workspace, '09170000003', '2026-07-19 08:00:00', '2026-07-18 08:00:00');

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => '09170000003',
            'type' => 'outgoing',
            'duration' => 60,
            'timestamp' => '2026-07-20T10:15:00+08:00',
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $call = CallLog::where('phone_number', '09170000003')->firstOrFail();

    expect($call->persona)->toBeNull()
        ->and($call->order_id)->toBeNull();
});

test('the delivery wins over an order confirmed the same day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Confirmed and dispatched the same day: the call is about the delivery.
    $order = confirmedOrderFor($workspace, '09170000004', '2026-07-20 08:00:00');

    $delivery = OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000004',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000004',
        'delivery_date' => '2026-07-20',
    ]);

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => fake()->unique()->numberBetween(1, 999999),
        'call_logs' => [[
            'phone_number' => '09170000004',
            'type' => 'outgoing',
            'duration' => 60,
            'timestamp' => '2026-07-20T10:15:00+08:00',
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $call = CallLog::where('phone_number', '09170000004')->firstOrFail();

    expect($call->persona)->toBe(CallLogPersona::CUSTOMER)
        ->and((int) $call->order_for_delivery_id)->toBe($delivery->id);
});
