<?php

use App\Jobs\BackfillCallLogPersonasForDay;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Support\CallLogPersona;
use Illuminate\Support\Facades\Queue;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * The backfill that stamps calls the sync could not.
 *
 * Two rules: the number was on a delivery loaded that day, or it was the phone
 * on an order confirmed that day. Deliveries land after the call is placed and
 * old rows predate the stamp entirely, which is why this runs after the fact.
 */
const BACKFILL_DATE = '2026-07-20';

function confirmedOrder($workspace, string $phone, string $confirmedAt): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create(['confirmed_at' => $confirmedAt]);

    ShippingAddress::factory()->create(['order_id' => $order->id, 'phone_number' => $phone]);

    return $order;
}

function unstampedCall($workspace, string $phone, array $overrides = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'phone_number' => $phone,
        'call_date' => BACKFILL_DATE,
        'persona' => null,
        'order_id' => null,
        'order_for_delivery_id' => null,
        ...$overrides,
    ]);
}

test('a call to a customer on the day their order was confirmed is stamped verification', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])
        ->assertSuccessful();

    $call->refresh();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id)
        // No delivery was involved, so there is no delivery row to point at.
        ->and($call->order_for_delivery_id)->toBeNull();
});

test('the phone is matched however either side spells it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // Pancake keeps this one international; the handset reported it local.
    $order = confirmedOrder($workspace, '+639170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    expect((int) $call->refresh()->order_id)->toBe($order->id);
});

test('an order confirmed on another day is not a verification match', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    confirmedOrder($workspace, '09170000001', '2026-07-19 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    $call->refresh();

    expect($call->persona)->toBeNull()
        ->and($call->order_id)->toBeNull();
});

test('an order confirmed in another workspace is not a match', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    confirmedOrder($other, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    expect($call->refresh()->persona)->toBeNull();
});

test('the delivery rule wins when a number matches both', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');

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
        'delivery_date' => BACKFILL_DATE,
    ]);

    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    $call->refresh();

    expect($call->persona)->toBe(CallLogPersona::CUSTOMER)
        ->and((int) $call->order_for_delivery_id)->toBe($delivery->id);
});

test('a row already stamped customer gets its missing delivery id filled in', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = Order::factory()->forWorkspace($workspace)->create(['confirmed_at' => null]);

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
        'delivery_date' => BACKFILL_DATE,
    ]);

    // Stamped back when the column did not exist yet.
    $call = unstampedCall($workspace, '09170000001', [
        'persona' => CallLogPersona::CUSTOMER,
        'order_id' => $order->id,
    ]);

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    expect((int) $call->refresh()->order_for_delivery_id)->toBe($delivery->id);
});

test('dry run writes nothing', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--dry-run' => true])
        ->assertSuccessful();

    expect($call->refresh()->persona)->toBeNull();
});

test('rule=delivery leaves verification matches alone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--rule' => 'delivery'])
        ->assertSuccessful();

    expect($call->refresh()->persona)->toBeNull();
});

test('ties go to the earliest confirmation', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $earlier = confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 08:00:00');
    confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 15:00:00');

    $call = unstampedCall($workspace, '09170000001');

    $this->artisan('call-logs:backfill-personas', ['--date' => BACKFILL_DATE, '--sync' => true])->assertSuccessful();

    expect((int) $call->refresh()->order_id)->toBe($earlier->id);
});

test('by default the work is queued, one job per workspace-day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');
    unstampedCall($workspace, '09170000002', ['call_date' => '2026-07-21']);

    Queue::fake();

    $this->artisan('call-logs:backfill-personas')->assertSuccessful();

    Queue::assertPushedOn('analytics', BackfillCallLogPersonasForDay::class);
    Queue::assertPushed(BackfillCallLogPersonasForDay::class, 2);

    // Queued, so nothing is written by the command itself.
    expect($call->refresh()->persona)->toBeNull();
});

test('a queued job stamps the day it was given', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    (new BackfillCallLogPersonasForDay($workspace->id, BACKFILL_DATE))->handle();

    $call->refresh();

    expect($call->persona)->toBe(CallLogPersona::VERIFICATION)
        ->and((int) $call->order_id)->toBe($order->id);
});

test('re-running a finished day writes nothing more', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    confirmedOrder($workspace, '09170000001', BACKFILL_DATE.' 09:00:00');
    $call = unstampedCall($workspace, '09170000001');

    (new BackfillCallLogPersonasForDay($workspace->id, BACKFILL_DATE))->handle();
    $stampedAt = $call->refresh()->updated_at;

    // Nothing is left to select, so the second pass is a read and no more.
    $totals = (new BackfillCallLogPersonasForDay($workspace->id, BACKFILL_DATE))->handle();

    expect($totals['scanned'])->toBe(0)
        ->and($call->refresh()->updated_at->eq($stampedAt))->toBeTrue();
});
