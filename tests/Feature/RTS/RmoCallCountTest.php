<?php

use App\Models\CallLog;
use App\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The call badge on an RMO row and the call-log modal it opens.
 *
 * The modal has always matched on workspace + phone + delivery date with no CSR
 * filter. The badge used to add "and the assignee placed it", so a reassigned or
 * unassigned order read "0 calls" and then opened onto a full history. These fix
 * the badge to the modal's set — while leaving the mobile KPI endpoints, which
 * report one CSR's own effort, scoped to that CSR.
 */
const CALL_DATE = '2026-07-20';

function deliveryRow($workspace, $assigneeId, string $customerPhone, string $riderPhone = '09180000001'): OrderForDelivery
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
        'assignee_id' => $assigneeId,
        'delivery_date' => CALL_DATE,
    ]);
}

function callTo(string $phone, $workspace, $callerId, array $overrides = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $callerId,
        'phone_number' => $phone,
        'call_date' => CALL_DATE,
        ...$overrides,
    ]);
}

/** The badge's numbers, as the RMO page builds them. */
function badgeFor(OrderForDelivery $delivery): array
{
    $row = OrderForDelivery::where('id', $delivery->id)
        ->withCount([
            'allCustomerCallLogs as customer_call_logs_count',
            'allRiderCallLogs as rider_call_logs_count',
        ])
        ->withSum('allCustomerCallLogs as customer_call_duration', 'duration')
        ->first();

    return [
        'attempts' => $row->customer_call_logs_count,
        'duration' => (int) $row->customer_call_duration,
        'rider_attempts' => $row->rider_call_logs_count,
    ];
}

it('counts calls placed by a CSR other than the assignee', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $assignee = PancakeUser::create(['name' => 'CSR A']);
    $other = PancakeUser::create(['name' => 'CSR B']);

    $delivery = deliveryRow($workspace, $assignee->id, '09170000001');

    foreach ([30, 45, 60] as $seconds) {
        callTo('09170000001', $workspace, $other->id, ['duration' => $seconds]);
    }

    expect(badgeFor($delivery))
        ->toMatchArray(['attempts' => 3, 'duration' => 135]);
});

it('counts calls on an order that has no assignee yet', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $caller = PancakeUser::create(['name' => 'CSR A']);
    $delivery = deliveryRow($workspace, null, '09170000001');

    callTo('09170000001', $workspace, $caller->id, ['duration' => 20]);
    callTo('09170000001', $workspace, $caller->id, ['duration' => 40]);

    // A null assignee matched nothing before, so this row read zero however many
    // times the customer had been rung.
    expect(badgeFor($delivery))->toMatchArray(['attempts' => 2, 'duration' => 60]);
});

it('counts a missed call as an attempt worth no talk time', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $caller = PancakeUser::create(['name' => 'CSR A']);
    $delivery = deliveryRow($workspace, $caller->id, '09170000001');

    callTo('09170000001', $workspace, $caller->id, ['type' => 'missed', 'duration' => 0]);

    expect(badgeFor($delivery))->toMatchArray(['attempts' => 1, 'duration' => 0]);
});

it('agrees with the list the modal shows for the same row', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $assignee = PancakeUser::create(['name' => 'CSR A']);
    $other = PancakeUser::create(['name' => 'CSR B']);

    $delivery = deliveryRow($workspace, $assignee->id, '09170000001');

    callTo('09170000001', $workspace, $assignee->id);
    callTo('09170000001', $workspace, $other->id);
    callTo('09170000001', $workspace, $other->id);

    $modal = $this->actingAs(makeWorkspaceMember($workspace))
        ->getJson(route('workspaces.csr.rmo-management.callLogs', [
            'workspace' => $workspace,
            'phone_number' => '09170000001',
            'date' => CALL_DATE,
        ]))->assertOk();

    expect(badgeFor($delivery)['attempts'])->toBe(count($modal->json()));
});

it('still ignores calls to another number or on another date', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $caller = PancakeUser::create(['name' => 'CSR A']);
    $delivery = deliveryRow($workspace, $caller->id, '09170000001');

    callTo('09170000009', $workspace, $caller->id);
    callTo('09170000001', $workspace, $caller->id, ['call_date' => '2026-07-19']);

    expect(badgeFor($delivery))->toMatchArray(['attempts' => 0, 'duration' => 0]);
});

it('keeps the customer and rider counts apart', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $caller = PancakeUser::create(['name' => 'CSR A']);
    $delivery = deliveryRow($workspace, $caller->id, '09170000001', '09180000002');

    callTo('09170000001', $workspace, $caller->id);
    callTo('09180000002', $workspace, $caller->id);
    callTo('09180000002', $workspace, $caller->id);

    expect(badgeFor($delivery))
        ->toMatchArray(['attempts' => 1, 'rider_attempts' => 2]);
});

it('leaves the mobile KPI reporting only the requesting CSR own calls', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $csr = PancakeUser::create(['name' => 'CSR A']);
    $other = PancakeUser::create(['name' => 'CSR B']);

    deliveryRow($workspace, $csr->id, '09170000001');

    callTo('09170000001', $workspace, $csr->id, ['duration' => 30]);
    callTo('09170000001', $workspace, $other->id, ['duration' => 90]);

    $response = $this->getJson(
        "/api/v1/public/call-logs/kpi?user_id={$csr->id}&date=".CALL_DATE,
        ['Authorization' => 'Bearer '.$raw]
    )->assertOk();

    // The badge would say 2 for this row; the KPI answers "how many did I make",
    // so the other CSR's call must not land in this CSR's numbers.
    expect($response->json('total_attempts'))->toBe(1)
        ->and($response->json('total_talk_time'))->toBe(30);
});
