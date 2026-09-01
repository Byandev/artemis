<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserPosDailyReport;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR Analytics stat cards.
 *
 * They read the workspace's orders through WorkspaceMetrics — the same
 * TotalSales/TotalOrders the main dashboard reports — rather than the per-CSR
 * daily rollup the table below them uses. The rollup is written nightly, so a
 * range it has not covered reads as zero on a day the dashboard shows plenty;
 * the card is there to answer "how did we do", so it answers with the figure
 * the rest of the app calls total sales.
 *
 * One endpoint per card, alongside the other /csrs/stats/* endpoints, because
 * each card measures the chosen range twice — once as itself, once against the
 * equally long period ending the day before it, which is what the up/down arrow
 * reports.
 */
function csrStatsContext(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    return ['owner' => $owner, 'workspace' => $workspace];
}

/**
 * A confirmed order counted by TotalSales.
 *
 * `confirmed_at` is the date column the metric filters on and `final_amount`
 * the figure it sums; statuses 6 and 7 are excluded as cancelled/removed.
 */
function confirmedOrder(Workspace $workspace, string $confirmedAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'final_amount' => $amount,
        'confirmed_at' => $confirmedAt,
    ]);
}

function csrStat($owner, Workspace $workspace, string $stat, string $from, string $to)
{
    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/{$stat}?from={$from}&to={$to}"
    );
}

function csrStats($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-sales', $from, $to);
}

function csrRtsStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-rts', $from, $to);
}

test('sales and orders come from the same source as the dashboard', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    confirmedOrder($workspace, '2026-08-01 09:00:00', 1000);
    confirmedOrder($workspace, '2026-08-03 14:00:00', 2500);
    // Outside the range on purpose.
    confirmedOrder($workspace, '2026-08-09 10:00:00', 9999);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 3500)
        ->assertJsonPath('orders', 2);
});

test('the whole last day of the range is included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Late on the closing day — a range compared as dates rather than
    // timestamps would drop this.
    confirmedOrder($workspace, '2026-08-05 23:30:00', 800);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 800)
        ->assertJsonPath('orders', 1);
});

test('cancelled orders are left out, as they are on the dashboard', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    confirmedOrder($workspace, '2026-08-02 10:00:00', 1000);

    Order::factory()->forWorkspace($workspace)->create([
        'status' => 6,
        'final_amount' => 5000,
        'confirmed_at' => '2026-08-02 11:00:00',
    ]);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1000)
        ->assertJsonPath('orders', 1);
});

test('the CSR daily rollup no longer feeds the card', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // The rollup being empty is exactly the case that made the card read zero
    // while the dashboard showed sales. The orders decide the figure now.
    expect(PancakeUserPosDailyReport::count())->toBe(0);

    confirmedOrder($workspace, '2026-08-02 10:00:00', 4200);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 4200);
});

test('the comparison window is the same length, ending the day before', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Aug 1-5 is five days, so it is measured against Jul 27-31.
    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('previous_period', ['from' => '2026-07-27', 'to' => '2026-07-31']);
});

test('a single-day range compares against the day before', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrStats($owner, $workspace, '2026-08-05', '2026-08-05')
        ->assertJsonPath('previous_period', ['from' => '2026-08-04', 'to' => '2026-08-04']);
});

test('the change is the percentage move on the previous period', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    confirmedOrder($workspace, '2026-07-28 10:00:00', 2000);
    confirmedOrder($workspace, '2026-08-02 10:00:00', 3000);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3000)
        ->assertJsonPath('previous_value', 2000)
        // Asserted as an int: JSON renders a whole float without its decimal.
        ->assertJsonPath('change', 50);
});

test('a fall reports a negative change', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    confirmedOrder($workspace, '2026-07-28 10:00:00', 4000);
    confirmedOrder($workspace, '2026-08-02 10:00:00', 1000);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('change', -75);
});

test('an empty previous period has no percentage rather than zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    confirmedOrder($workspace, '2026-08-02 10:00:00', 3000);

    // 0% would read as "flat"; this is the first period with any sales at all.
    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('previous_value', 0)
        ->assertJsonPath('change', null);
});

test('another workspace\'s orders are not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    confirmedOrder($other, '2026-08-02 10:00:00', 7000);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('orders', 0);
});

test('the endpoint needs the CSR analytics permission', function () {
    ['workspace' => $workspace] = csrStatsContext();

    $outsider = User::factory()->create();
    $workspace->users()->attach($outsider->id);

    foreach (['analytics-sales', 'analytics-rts', 'analytics-rmo-called', 'analytics-rmo-time', 'analytics-calls-placed', 'analytics-real-conversations', 'analytics-reach-rate', 'analytics-longest-call'] as $stat) {
        $this->actingAs($outsider)
            ->getJson("/api/workspaces/{$workspace->slug}/csrs/stats/{$stat}?from=2026-08-01&to=2026-08-05")
            ->assertForbidden();
    }
});

/**
 * A parcel that came back, counted by RtsRate's numerator: `returning_at` set,
 * and not a cancelled status.
 */
function returningOrder(Workspace $workspace, string $returningAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 4,
        'final_amount' => $amount,
        'returning_at' => $returningAt,
    ]);
}

/** A parcel that arrived — RtsRate's denominator, with the returning amount. */
function deliveredOrder(Workspace $workspace, string $deliveredAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 3,
        'final_amount' => $amount,
        'delivered_at' => $deliveredAt,
    ]);
}

test('the RTS rate is returning over returning plus delivered, as on the dashboard', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-03 10:00:00', 8000);

    // 2000 / (2000 + 8000) = 20%
    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('returning_amount', 2000);
});

test('the RTS change is reported in percentage points, not a relative move', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Previous period: 10%. Current: 20%. That is +10 points, not +100%.
    returningOrder($workspace, '2026-07-28 10:00:00', 1000);
    deliveredOrder($workspace, '2026-07-29 10:00:00', 9000);

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-03 10:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('previous_value', 10)
        ->assertJsonPath('change', 10);
});

test('a range where nothing settled has no rate rather than a perfect one', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Sales happened, but nothing was delivered or returned yet. A 0% RTS here
    // would read as a flawless period rather than an unfinished one.
    confirmedOrder($workspace, '2026-08-02 10:00:00', 5000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);
});

test('an empty previous period leaves the RTS comparison undefined', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-03 10:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('previous_value', null)
        ->assertJsonPath('change', null);
});

function csrRmoStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-rmo-called', $from, $to);
}

/** The CSR every assigned delivery in these tests belongs to. */
function rmoAssignee(): PancakeUser
{
    return PancakeUser::firstOrCreate(['name' => 'RMO CSR']);
}

/** An RMO delivery row. Pass $assigned false for one nobody owns. */
function rmoDelivery(Workspace $workspace, string $date, string $status, bool $assigned = true): void
{
    $order = Order::factory()->forWorkspace($workspace)->create();

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => $status,
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'assignee_id' => $assigned ? rmoAssignee()->id : null,
        'delivery_date' => $date,
    ]);
}

test('RMO called % is the called share of the assigned deliveries', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Three assigned, two off PENDING.
    rmoDelivery($workspace, '2026-08-02', 'CALLED');
    rmoDelivery($workspace, '2026-08-03', 'ANSWERED');
    rmoDelivery($workspace, '2026-08-03', 'PENDING');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('called', 2)
        ->assertJsonPath('assigned', 3)
        ->assertJsonPath('value', 66.67);
});

test('unassigned deliveries are not counted against the rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoDelivery($workspace, '2026-08-02', 'CALLED');
    // Nobody's to call, so it must not drag the rate down.
    rmoDelivery($workspace, '2026-08-02', 'PENDING', assigned: false);

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('assigned', 1)
        ->assertJsonPath('value', 100);
});

test('deliveries outside the range are left out', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoDelivery($workspace, '2026-08-02', 'CALLED');
    rmoDelivery($workspace, '2026-08-09', 'PENDING');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('assigned', 1)
        ->assertJsonPath('called', 1);
});

test('the RMO change is reported in percentage points', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Previous period: 1 of 2 called = 50%. Current: 2 of 2 = 100%. +50 pts.
    rmoDelivery($workspace, '2026-07-28', 'CALLED');
    rmoDelivery($workspace, '2026-07-28', 'PENDING');

    rmoDelivery($workspace, '2026-08-02', 'CALLED');
    rmoDelivery($workspace, '2026-08-03', 'CALLED');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 100)
        ->assertJsonPath('previous_value', 50)
        ->assertJsonPath('change', 50);
});

test('a range with nothing assigned has no rate rather than zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // 0% would read as "nobody rang anyone" rather than "nothing to ring".
    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);
});

test('another workspace\'s deliveries are not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    rmoDelivery($other, '2026-08-02', 'CALLED');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('assigned', 0)
        ->assertJsonPath('value', null);
});

function csrRmoTimeStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-rmo-time', $from, $to);
}

/**
 * A logged call of $seconds on $date.
 *
 * Carries an order_id by default — that is what marks it as an RMO call. Pass
 * $matched false for one that reached a number belonging to no delivery.
 */
function rmoCall(Workspace $workspace, string $date, int $seconds, bool $matched = true): void
{
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => rmoAssignee()->id,
        'phone_number' => '09170000001',
        'call_date' => $date,
        'duration' => $seconds,
        'order_id' => $matched
            ? Order::factory()->forWorkspace($workspace)->create()->id
            : null,
    ]);
}

test('RMO total time is the talk time across the range\'s RMO calls', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 120);
    rmoCall($workspace, '2026-08-03', 60);
    // Outside the range on purpose.
    rmoCall($workspace, '2026-08-09', 9999);

    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 180)
        ->assertJsonPath('calls', 2)
        ->assertJsonPath('average_seconds', 90);
});

test('the average is talk time over calls placed, not over the range', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 100);
    rmoCall($workspace, '2026-08-02', 50);
    rmoCall($workspace, '2026-08-02', 0);

    // 150 seconds over 3 calls — the unanswered one still counts as an attempt.
    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('calls', 3)
        ->assertJsonPath('average_seconds', 50);
});

test('the time change is relative, not in points', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-07-28', 100);
    rmoCall($workspace, '2026-08-02', 150);

    // A duration is a magnitude, so +50% is the readable form.
    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 150)
        ->assertJsonPath('previous_value', 100)
        ->assertJsonPath('change', 50);
});

test('a range with no calls has no average and no comparison', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('calls', 0)
        ->assertJsonPath('average_seconds', null)
        ->assertJsonPath('change', null);
});

test('another workspace\'s calls are not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    rmoCall($other, '2026-08-02', 300);

    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('calls', 0);
});

test('a call matched to no order is not RMO time', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 120);
    // Reached a number on no delivery, so it is not time spent on RMO.
    rmoCall($workspace, '2026-08-02', 600, matched: false);

    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 120)
        ->assertJsonPath('calls', 1);
});

function csrCallsPlacedStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-calls-placed', $from, $to);
}

test('calls placed counts every call against an order, however short', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 5);
    // A one-second call still counts as placed — the CSR rang and was answered.
    rmoCall($workspace, '2026-08-02', 1);
    // Zero seconds never joined: it rang out or the line was busy.
    rmoCall($workspace, '2026-08-03', 0);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 4)
        ->assertJsonPath('connected', 3);
});

test('a call matched to no order is not a call placed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 60, matched: false);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('connected', 1);
});

test('the calls placed change is relative', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-07-28', 30);
    rmoCall($workspace, '2026-07-29', 30);

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-03', 30);

    // 2 to 3 calls is +50%.
    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3)
        ->assertJsonPath('previous_value', 2)
        ->assertJsonPath('change', 50);
});

test('a one-second call counts as both placed and connected', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 1);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('connected', 1)
        ->assertJsonPath('change', null);
});

test('a call that never joined is placed but not connected', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 0);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('connected', 0);
});

test('connected here is looser than the RMO page\'s five-second rule', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // The RMO page counts a call as connected only at 5s+. This card counts any
    // talk time at all, so the two figures differ on purpose — pinned here so
    // the divergence stays deliberate rather than becoming a surprise.
    rmoCall($workspace, '2026-08-02', 2);

    expect(RmoDailyStats::CONNECTED_CALL_MIN_SECONDS)->toBe(5);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('connected', 1);
});

function csrRealConversationsStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-real-conversations', $from, $to);
}

test('real conversations counts only calls of five seconds or more', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 5);
    // A hello and a hang-up is not a conversation.
    rmoCall($workspace, '2026-08-02', 4);
    rmoCall($workspace, '2026-08-03', 1);
    rmoCall($workspace, '2026-08-03', 0);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 2)
        ->assertJsonPath('placed', 5)
        ->assertJsonPath('share', 40);

    // The same ratio is the Reach Rate card's headline.
    csrReachRateStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 40);
});

test('the three call figures narrow in turn', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 2);
    rmoCall($workspace, '2026-08-02', 0);

    // Placed counts every attempt, connected any talk time, real 5s+.
    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3)
        ->assertJsonPath('connected', 2);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1);
});

test('a call matched to no order is not a real conversation', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 60, matched: false);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('placed', 1);
});

test('a range with no calls placed has no share', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('placed', 0)
        ->assertJsonPath('share', null)
        ->assertJsonPath('change', null);
});

function csrReachRateStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-reach-rate', $from, $to);
}

test('reach rate is real conversations over calls placed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // 1 of 4 attempts lasted five seconds — 25%.
    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-02', 4);
    rmoCall($workspace, '2026-08-02', 1);
    rmoCall($workspace, '2026-08-03', 0);

    csrReachRateStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 25)
        ->assertJsonPath('real', 1)
        ->assertJsonPath('placed', 4);
});

test('the reach rate change is in percentage points', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Previous: 1 of 2 = 50%. Current: 2 of 2 = 100%. +50 pts.
    rmoCall($workspace, '2026-07-28', 30);
    rmoCall($workspace, '2026-07-28', 1);

    rmoCall($workspace, '2026-08-02', 30);
    rmoCall($workspace, '2026-08-03', 30);

    csrReachRateStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 100)
        ->assertJsonPath('previous_value', 50)
        ->assertJsonPath('change', 50);
});

test('a range with no attempts has no reach rate rather than zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // 0% would read as "rang all day and reached nobody".
    csrReachRateStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);
});

function csrLongestCallStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-longest-call', $from, $to);
}

test('longest call is the single longest in the range, with the day it landed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 90);
    rmoCall($workspace, '2026-08-03', 750);
    rmoCall($workspace, '2026-08-04', 120);
    // Outside the range, and longer — must not win.
    rmoCall($workspace, '2026-08-09', 9999);

    csrLongestCallStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 750)
        ->assertJsonPath('call_date', '2026-08-03');
});

test('an unmatched call cannot be the longest', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 60);
    rmoCall($workspace, '2026-08-02', 6000, matched: false);

    csrLongestCallStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 60);
});

test('the longest call change is relative', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-07-28', 100);
    rmoCall($workspace, '2026-08-02', 150);

    csrLongestCallStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 150)
        ->assertJsonPath('previous_value', 100)
        ->assertJsonPath('change', 50);
});

test('a range with no calls has no longest and no day', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrLongestCallStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('call_date', null)
        ->assertJsonPath('change', null);
});
