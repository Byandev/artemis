<?php

use App\Jobs\SyncCsrDailyCallRecord;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserDailyCallReport;
use App\Models\Shop;
use App\Support\CallLogPersona;
use App\Support\RmoDailyStats;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * A CSR's calls for a day, one row per shop.
 *
 * The order on a call names its shop; the delivery id says whether it was RMO
 * work or order verification, and the persona splits the RMO half into customer
 * and rider.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->csr = PancakeUser::create(['name' => 'Angeline Mercado']);
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** An order in the given shop, or the shared one. */
function callOrder(?Shop $shop = null): Order
{
    return Order::factory()->forWorkspace(test()->workspace)->create([
        'shop_id' => ($shop ?? test()->shop)->id,
    ]);
}

/**
 * A call by the CSR about $order.
 *
 * The order names the shop; the delivery id is what makes it RMO work rather
 * than order verification, so $delivery is what the two cases differ by.
 */
function csrCall(
    ?Order $order,
    string $persona = CallLogPersona::CUSTOMER,
    int $duration = 60,
    string $at = '10:00:00',
    ?int $delivery = 1,
): CallLog {
    return CallLog::create([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->csr->id,
        'phone_number' => '09170000001',
        'type' => 'outgoing',
        'duration' => $duration,
        'call_date' => '2026-08-02',
        'call_time' => $at,
        'order_id' => $order?->id,
        'order_for_delivery_id' => $order ? $delivery : null,
        'persona' => $order ? $persona : null,
    ]);
}

function callReport(): ?PancakeUserDailyCallReport
{
    return PancakeUserDailyCallReport::where('pancake_user_id', test()->csr->id)->first();
}

test('a day of calls is counted and timed against the shop', function () {
    $order = callOrder();

    csrCall($order, CallLogPersona::CUSTOMER, 120, '10:00:00');
    csrCall($order, CallLogPersona::CUSTOMER, 45, '11:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->shop_id)->toBe((int) $this->shop->id)
        ->and((int) $row->total_called)->toBe(2)
        ->and((int) $row->total_call_time)->toBe(165);
});

test('the customer and rider calls are split, and add up to the RMO figures', function () {
    $order = callOrder();

    csrCall($order, CallLogPersona::CUSTOMER, 100, '10:00:00');
    csrCall($order, CallLogPersona::CUSTOMER, 50, '10:30:00');
    csrCall($order, CallLogPersona::RIDER, 30, '11:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->total_rmo_customer_called)->toBe(2)
        ->and((int) $row->total_rmo_customer_call_time)->toBe(150)
        ->and((int) $row->total_rmo_rider_called)->toBe(1)
        ->and((int) $row->total_rmo_rider_call_time)->toBe(30)
        // The two personas are the whole of the RMO calls.
        ->and((int) $row->total_rmo_called)->toBe(3)
        ->and((int) $row->total_rmo_call_time)->toBe(180);
});

test('the RMO orders count the deliveries, however often each was rung', function () {
    $order = callOrder();

    // One parcel rung twice — the customer, then the rider — and a second rung
    // once. Three calls, two deliveries.
    csrCall($order, CallLogPersona::CUSTOMER, 100, '10:00:00', delivery: 1);
    csrCall($order, CallLogPersona::RIDER, 50, '10:30:00', delivery: 1);
    csrCall($order, CallLogPersona::CUSTOMER, 30, '11:00:00', delivery: 2);
    // No delivery on it, so nothing to count: this is verification work.
    csrCall($order, CallLogPersona::CUSTOMER, 40, '12:00:00', delivery: null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->total_rmo_called)->toBe(3)
        ->and((int) $row->total_rmo_orders)->toBe(2);
});

test('a call about no order is not recorded at all', function () {
    csrCall(null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    // No order means no shop to file it under, and shop is part of the key.
    expect(PancakeUserDailyCallReport::count())->toBe(0);
});

test('a call about an order but no delivery is verification, not RMO', function () {
    $order = callOrder();

    csrCall($order, CallLogPersona::CUSTOMER, 60, '10:00:00');
    csrCall($order, CallLogPersona::CUSTOMER, 90, '11:00:00', delivery: null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->total_verification_called)->toBe(1)
        ->and((int) $row->total_verification_call_time)->toBe(90)
        ->and((int) $row->total_rmo_called)->toBe(1)
        ->and((int) $row->total_rmo_call_time)->toBe(60)
        // The two together are the CSR's whole day on that shop.
        ->and((int) $row->total_called)->toBe(2)
        ->and((int) $row->total_call_time)->toBe(150);
});

test('a verification call that lasted is counted as a real conversation', function () {
    $order = callOrder();

    csrCall($order, CallLogPersona::CUSTOMER, 90, '10:00:00', delivery: null);
    csrCall($order, CallLogPersona::CUSTOMER, RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1, '11:00:00', delivery: null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    // The same five-second cut the RMO side makes, so the two can be drawn on
    // one axis without meaning different things by "real".
    expect((int) $row->total_verification_called)->toBe(2)
        ->and((int) $row->total_verification_real_called)->toBe(1);
});

test('an order rung more than once is one verified order, not three', function () {
    $order = callOrder();

    // The calls and the orders are counted separately on purpose: read against
    // the orders that needed verifying, only the distinct count is a coverage
    // figure — the calls put two orders rung three times between them at 150%.
    csrCall($order, CallLogPersona::CUSTOMER, 60, '10:00:00', delivery: null);
    csrCall($order, CallLogPersona::CUSTOMER, 30, '11:00:00', delivery: null);
    csrCall($order, CallLogPersona::CUSTOMER, 45, '12:00:00', delivery: null);
    csrCall(callOrder(), CallLogPersona::CUSTOMER, 20, '13:00:00', delivery: null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->total_verification_called)->toBe(4)
        ->and((int) $row->total_verified_orders)->toBe(2);
});

test('an order rung about a delivery is not a verified order', function () {
    $order = callOrder();

    // Stamped to a delivery, so it is RMO work — the CSR chasing the parcel
    // rather than confirming the order.
    csrCall($order, CallLogPersona::CUSTOMER, 60, '10:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    expect((int) callReport()->total_verified_orders)->toBe(0);
});

test('a verification call is not counted against either persona', function () {
    $order = callOrder();

    csrCall($order, CallLogPersona::CUSTOMER, 90, '11:00:00', delivery: null);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    // The persona split describes RMO work; a call about no delivery is not it,
    // whatever the ingest stamp happened to leave on the row.
    expect((int) $row->total_rmo_customer_called)->toBe(0)
        ->and((int) $row->total_rmo_rider_called)->toBe(0)
        ->and((int) $row->total_verification_called)->toBe(1);
});

test('calls about two shops get a row each', function () {
    $other = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    csrCall(callOrder(), CallLogPersona::CUSTOMER, 60, '10:00:00');
    csrCall(callOrder($other), CallLogPersona::RIDER, 20, '11:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $rows = PancakeUserDailyCallReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows->firstWhere('shop_id', $this->shop->id)->total_call_time)->toBe(60)
        ->and((int) $rows->firstWhere('shop_id', $other->id)->total_rmo_rider_called)->toBe(1);
});

test('another day of calls is left alone', function () {
    csrCall(callOrder(), CallLogPersona::CUSTOMER, 60, '10:00:00');

    CallLog::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->csr->id,
        'phone_number' => '09170000002',
        'type' => 'outgoing',
        'duration' => 900,
        'call_date' => '2026-08-03',
        'call_time' => '10:00:00',
        'order_id' => callOrder()->id,
        'persona' => CallLogPersona::CUSTOMER,
    ]);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    expect((int) callReport()->total_call_time)->toBe(60);
});

test('re-running the sync updates the row rather than adding another', function () {
    csrCall(callOrder(), CallLogPersona::CUSTOMER, 60, '10:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();
    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    expect(PancakeUserDailyCallReport::count())->toBe(1)
        ->and((int) callReport()->total_called)->toBe(1);
});

test('a scoped rebuild leaves the other workspaces alone', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $theirCsr = PancakeUser::create(['name' => 'Elsewhere CSR']);
    $theirOrder = Order::factory()->forWorkspace($other)->create();

    CallLog::create([
        'workspace_id' => $other->id,
        'user_id' => $theirCsr->id,
        'phone_number' => '09170000003',
        'type' => 'outgoing',
        'duration' => 60,
        'call_date' => '2026-08-02',
        'call_time' => '10:00:00',
        'order_id' => $theirOrder->id,
        'persona' => CallLogPersona::CUSTOMER,
    ]);

    csrCall(callOrder(), CallLogPersona::CUSTOMER, 60, '10:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02', $this->workspace->id))->handle();

    expect(PancakeUserDailyCallReport::where('workspace_id', $this->workspace->id)->count())->toBe(1)
        ->and(PancakeUserDailyCallReport::where('workspace_id', $other->id)->count())->toBe(0);
});

test('the command takes the workspace by slug or by id', function () {
    foreach ([$this->workspace->slug, (string) $this->workspace->id] as $option) {
        $this->artisan('sync:csr-daily-call-records', [
            '--date' => '2026-08-02',
            '--workspace' => $option,
        ])->assertSuccessful();
    }
});

test('the command refuses a workspace it cannot find', function () {
    $this->artisan('sync:csr-daily-call-records', [
        '--date' => '2026-08-02',
        '--workspace' => 'no-such-workspace',
    ])->assertFailed();
});

/**
 * The deliveries beside the calls: what the CSR was handed that day, and what
 * they confirmed as out for delivery. Counted from pancake_order_for_delivery,
 * so a CSR who was given work and never rang anyone still gets a row.
 */
function callReportDelivery(
    ?PancakeUser $assignee = null,
    ?PancakeUser $conferrer = null,
    string $status = 'CALLED',
    ?Shop $shop = null,
): OrderForDelivery {
    $order = callOrder($shop);

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => test()->workspace->id,
        'status' => $status,
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'assignee_id' => $assignee?->id,
        'conferrer_id' => $conferrer?->id,
        'delivery_date' => '2026-08-02',
    ]);
}

test('every delivery assigned that day is counted, PENDING or not', function () {
    callReportDelivery(assignee: $this->csr, status: 'CALLED');
    callReportDelivery(assignee: $this->csr, status: 'PENDING');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    // The work given, not the work got through — the RMO rollup beside this one
    // is where the non-PENDING count lives.
    expect((int) callReport()->total_rmo_assigned_count)->toBe(2);
});

test('the deliveries the CSR confirmed are counted separately', function () {
    $someoneElse = PancakeUser::create(['name' => 'Someone Else']);

    callReportDelivery(assignee: $someoneElse, conferrer: $this->csr);
    callReportDelivery(assignee: $this->csr, conferrer: $someoneElse);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect((int) $row->total_rmo_confirmed_count)->toBe(1)
        ->and((int) $row->total_rmo_assigned_count)->toBe(1);
});

test('a CSR given deliveries but making no calls still gets a row', function () {
    callReportDelivery(assignee: $this->csr);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $row = callReport();

    expect($row)->not->toBeNull()
        ->and((int) $row->total_rmo_assigned_count)->toBe(1)
        // Which is the gap the row is there to show.
        ->and((int) $row->total_called)->toBe(0)
        ->and((int) $row->total_call_time)->toBe(0);
});

test('deliveries and calls in one shop land on one row', function () {
    $delivery = callReportDelivery(assignee: $this->csr, conferrer: $this->csr);

    csrCall(Order::find($delivery->order_id), CallLogPersona::CUSTOMER, 90, '10:00:00');

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $rows = PancakeUserDailyCallReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->total_rmo_assigned_count)->toBe(1)
        ->and((int) $rows->first()->total_rmo_confirmed_count)->toBe(1)
        ->and((int) $rows->first()->total_called)->toBe(1)
        ->and((int) $rows->first()->total_call_time)->toBe(90);
});

test('deliveries in two shops are counted against each', function () {
    $other = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    callReportDelivery(assignee: $this->csr);
    callReportDelivery(assignee: $this->csr, shop: $other);
    callReportDelivery(assignee: $this->csr, shop: $other);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    $rows = PancakeUserDailyCallReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows->firstWhere('shop_id', $this->shop->id)->total_rmo_assigned_count)->toBe(1)
        ->and((int) $rows->firstWhere('shop_id', $other->id)->total_rmo_assigned_count)->toBe(2);
});

test('another day\'s deliveries are left alone', function () {
    $order = callOrder();

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $this->workspace->id,
        'status' => 'CALLED',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'assignee_id' => $this->csr->id,
        'delivery_date' => '2026-08-03',
    ]);

    callReportDelivery(assignee: $this->csr);

    (new SyncCsrDailyCallRecord('2026-08-02'))->handle();

    expect((int) callReport()->total_rmo_assigned_count)->toBe(1);
});
