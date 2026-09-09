<?php

use App\Models\CallLog;
use App\Models\Order as AppOrder;
use App\Models\Page;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use App\Support\CallLogPersona;
use Modules\Pancake\Actions\LinkVerificationCallLogsAction;
use Modules\Pancake\Models\Order;

/**
 * An order claiming the calls that were waiting for it.
 *
 * Calls and orders sync on their own schedules. A CSR rings a customer to
 * verify an order, the call syncs first, and CallLogPersona finds no order to
 * match it to — so the row lands unmatched. When the order itself arrives it
 * runs the same rule backwards and picks those calls up, rather than leaving
 * them for the nightly backfill.
 *
 * Today's confirmations only, so the dates here are all relative to now rather
 * than written out: a fixed date would stop being today tomorrow.
 */
function orderConfirmedOn(Workspace $workspace, mixed $confirmedAt, string $phone): Order
{
    $page = Page::factory()->forWorkspace($workspace)->create();

    // The app Order model owns the factory; the action consumes the module
    // Order. Both map to pancake_orders, so create with one and re-read as the
    // other.
    $order = Order::findOrFail(
        AppOrder::factory()->forPage($page)->create(['confirmed_at' => $confirmedAt])->id
    );

    ShippingAddress::factory()->create([
        'order_id' => $order->id,
        'phone_number' => $phone,
    ]);

    return $order;
}

function unmatchedCall(Workspace $workspace, string $phone, mixed $date, array $attributes = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'phone_number' => $phone,
        'call_date' => $date,
        'order_id' => null,
        'persona' => null,
        ...$attributes,
    ]);
}

it('claims the unmatched calls made to the customer on the day the order was confirmed', function () {
    $workspace = Workspace::factory()->create();

    // Spelled the way Pancake gave it; the call carries the local spelling of
    // the same subscriber, which is the whole reason both sides are normalized.
    $order = orderConfirmedOn($workspace, now()->setTime(14, 5), '+639171234567');

    $call = unmatchedCall($workspace, '09171234567', today());

    $claimed = app(LinkVerificationCallLogsAction::class)->execute($order);

    expect($claimed)->toBe(1);
    expect($call->refresh())
        ->order_id->toBe($order->id)
        ->persona->toBe(CallLogPersona::VERIFICATION);
});

it('leaves calls on another day, another number, another workspace and another order alone', function () {
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();

    $order = orderConfirmedOn($workspace, now()->setTime(14, 5), '09171234567');

    $wrongDay = unmatchedCall($workspace, '09171234567', today()->subDay());
    $wrongNumber = unmatchedCall($workspace, '09170000000', today());
    $wrongWorkspace = unmatchedCall($other, '09171234567', today());
    // Already matched to a delivery: that call is about the delivery in front
    // of it, and a verification match does not outrank one.
    $alreadyMatched = unmatchedCall($workspace, '09171234567', today(), [
        'order_id' => 999_999,
        'persona' => CallLogPersona::CUSTOMER,
    ]);

    expect(app(LinkVerificationCallLogsAction::class)->execute($order))->toBe(0);

    foreach ([$wrongDay, $wrongNumber, $wrongWorkspace] as $untouched) {
        expect($untouched->refresh())->order_id->toBeNull()->persona->toBeNull();
    }

    expect($alreadyMatched->refresh())
        ->order_id->toBe(999_999)
        ->persona->toBe(CallLogPersona::CUSTOMER);
});

it('does nothing for an order that was never confirmed', function () {
    $workspace = Workspace::factory()->create();

    $order = orderConfirmedOn($workspace, now()->setTime(14, 5), '09171234567');
    $order->update(['confirmed_at' => null]);

    $call = unmatchedCall($workspace, '09171234567', today());

    expect(app(LinkVerificationCallLogsAction::class)->execute($order))->toBe(0);
    expect($call->refresh())->order_id->toBeNull();
});

it('does nothing when the shipping address has no number to match on', function () {
    $workspace = Workspace::factory()->create();

    // Too short to be a number: normalize() refuses to pad it into something
    // that could collide with a real one.
    $order = orderConfirmedOn($workspace, now()->setTime(14, 5), '12345');

    $call = unmatchedCall($workspace, '12345', today());

    expect(app(LinkVerificationCallLogsAction::class)->execute($order))->toBe(0);
    expect($call->refresh())->order_id->toBeNull();
});

it('leaves an order confirmed on an earlier day to the backfill', function () {
    $workspace = Workspace::factory()->create();

    // Same customer, same number, and a call sitting unmatched on the day it
    // was confirmed — but that day is behind us, so this is not the gap the
    // action is here to close.
    $order = orderConfirmedOn($workspace, today()->subDay()->setTime(14, 5), '09171234567');

    $call = unmatchedCall($workspace, '09171234567', today()->subDay());

    expect(app(LinkVerificationCallLogsAction::class)->execute($order))->toBe(0);
    expect($call->refresh())->order_id->toBeNull()->persona->toBeNull();
});
