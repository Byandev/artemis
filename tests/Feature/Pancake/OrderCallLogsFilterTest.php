<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use App\Support\CallLogPersona;

/**
 * The Orders list's Call logs filter.
 *
 * Whether anyone in the workspace has rung the number on the order. Matched on
 * the number rather than on call_logs.order_id, because a call only earns an
 * order id where the sync could place it — so a customer who was called can sit
 * next to an order carrying nothing.
 */

/** @return array<int, string> the order numbers the page rendered */
function callLogRows(Workspace $workspace, array $filter = []): array
{
    $rows = test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]))->assertOk()->viewData('page')['props']['orders']['data'];

    return collect($rows)->pluck('order_number')->all();
}

function orderWithPhone(Workspace $workspace, string $number, ?string $phone): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => $number]);

    if ($phone !== null) {
        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'phone_number' => $phone,
        ]);
    }

    return $order;
}

function logCall(Workspace $workspace, string $phone, ?string $persona = null): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'phone_number' => $phone,
        // Null is a real state, not a missing one: the sync could place the
        // call as neither a delivery nor a verification.
        'persona' => $persona,
    ]);
}

it('finds the orders whose number has been called, and those it has not', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'CALLED', '09171234567');
    orderWithPhone($workspace, 'QUIET', '09180000000');

    logCall($workspace, '09171234567');

    expect(callLogRows($workspace, ['call_logs' => 'has']))->toBe(['CALLED'])
        ->and(callLogRows($workspace, ['call_logs' => 'none']))->toBe(['QUIET']);
});

it('matches however either side spelled the number', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    // The same subscriber, written three ways — which is what the two tables
    // actually hold: Pancake keeps whatever it was given, and so does the
    // handset the call came off.
    orderWithPhone($workspace, 'PLUS-63', '+639171234567');
    orderWithPhone($workspace, 'NO-PREFIX', '917 123 4567');

    logCall($workspace, '09171234567');

    expect(callLogRows($workspace, ['call_logs' => 'has']))
        ->toEqualCanonicalizing(['PLUS-63', 'NO-PREFIX']);
});

it('counts an order with no address as one that has not been called', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'NO-ADDRESS', null);

    expect(callLogRows($workspace, ['call_logs' => 'none']))->toBe(['NO-ADDRESS'])
        ->and(callLogRows($workspace, ['call_logs' => 'has']))->toBe([]);
});

it('does not count another workspace calls', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'ORD-1', '09171234567');

    logCall($other, '09171234567');

    expect(callLogRows($workspace, ['call_logs' => 'has']))->toBe([])
        ->and(callLogRows($workspace, ['call_logs' => 'none']))->toBe(['ORD-1']);
});

it('tells a delivery call from a verification one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'RMO-CX', '09170000001');
    orderWithPhone($workspace, 'RMO-RIDER', '09170000002');
    orderWithPhone($workspace, 'VERIFIED', '09170000003');

    logCall($workspace, '09170000001', CallLogPersona::CUSTOMER);
    logCall($workspace, '09170000002', CallLogPersona::RIDER);
    logCall($workspace, '09170000003', CallLogPersona::VERIFICATION);

    // Both RMO personas count as one kind — the same pair RmoDailyStats counts
    // a day's calls by.
    expect(callLogRows($workspace, ['call_logs' => 'has', 'call_logs_kind' => 'rmo']))
        ->toEqualCanonicalizing(['RMO-CX', 'RMO-RIDER'])
        ->and(callLogRows($workspace, ['call_logs' => 'has', 'call_logs_kind' => 'verification']))
        ->toBe(['VERIFIED'])
        ->and(callLogRows($workspace, ['call_logs' => 'has', 'call_logs_kind' => 'any']))
        ->toEqualCanonicalizing(['RMO-CX', 'RMO-RIDER', 'VERIFIED']);
});

it('reads an unplaced call as one of no particular kind', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'ORD-1', '09171234567');

    // The sync could match it to neither a delivery nor a just-taken order.
    logCall($workspace, '09171234567', persona: null);

    expect(callLogRows($workspace, ['call_logs' => 'has']))->toBe(['ORD-1'])
        ->and(callLogRows($workspace, ['call_logs' => 'has', 'call_logs_kind' => 'rmo']))->toBe([])
        ->and(callLogRows($workspace, ['call_logs' => 'has', 'call_logs_kind' => 'verification']))->toBe([]);
});

it('reads no calls of a kind as having none of that kind alone', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'VERIFIED-ONLY', '09171234567');

    logCall($workspace, '09171234567', CallLogPersona::VERIFICATION);

    // Called, but never for a delivery — so it answers both of these.
    expect(callLogRows($workspace, ['call_logs' => 'none', 'call_logs_kind' => 'rmo']))
        ->toBe(['VERIFIED-ONLY'])
        ->and(callLogRows($workspace, ['call_logs' => 'none']))->toBe([]);
});

it('leaves the list alone when the answer is not one it knows', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    orderWithPhone($workspace, 'ORD-1', '09171234567');
    orderWithPhone($workspace, 'ORD-2', '09180000000');

    expect(callLogRows($workspace, ['call_logs' => 'maybe']))
        ->toEqualCanonicalizing(['ORD-1', 'ORD-2']);
});
