<?php

use App\Jobs\SyncCsrDailyRecord;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserPosDailyReport;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

    foreach (['analytics-sales', 'analytics-rts', 'analytics-rmo-called', 'analytics-rmo-time', 'analytics-calls-placed', 'analytics-real-conversations', 'analytics-reach-rate', 'analytics-longest-call', 'analytics-leader-sales', 'analytics-leader-rts', 'analytics-leader-rmo-called', 'analytics-leader-rmo-duration'] as $stat) {
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

/**
 * Run the nightly POS rollup across a range, as the scheduler does. The leader
 * cards read it, so the tests seed orders and then write the rollup for real.
 */
function syncPosRollup(string $from, string $to): void
{
    $cursor = CarbonImmutable::parse($from);
    $end = CarbonImmutable::parse($to);

    while ($cursor->lessThanOrEqualTo($end)) {
        (new SyncCsrDailyRecord($cursor->toDateString()))->handle();
        $cursor = $cursor->addDay();
    }
}

function csrSalesLeader($owner, Workspace $workspace, string $from, string $to)
{
    syncPosRollup($from, $to);

    return csrStat($owner, $workspace, 'analytics-leader-sales', $from, $to);
}

/** A confirmed order credited to $csr via pancake_orders.confirmed_by. */
function orderConfirmedBy(Workspace $workspace, PancakeUser $csr, string $confirmedAt, float $amount): void
{
    Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'final_amount' => $amount,
        'confirmed_at' => $confirmedAt,
        'confirmed_by' => $csr->id,
    ]);
}

test('the sales leader is the CSR who confirmed the most, with their AOV', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $top = PancakeUser::create(['name' => 'Angeline Mercado']);
    $other = PancakeUser::create(['name' => 'Someone Else']);

    orderConfirmedBy($workspace, $top, '2026-08-02 10:00:00', 6000);
    orderConfirmedBy($workspace, $top, '2026-08-03 10:00:00', 2000);
    orderConfirmedBy($workspace, $other, '2026-08-02 11:00:00', 2000);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader.name', 'Angeline Mercado')
        ->assertJsonPath('leader.value', 8000)
        ->assertJsonPath('leader.orders', 2)
        ->assertJsonPath('leader.aov', 4000)
        // 8000 of 10000 confirmed across the workspace.
        ->assertJsonPath('leader.share', 80);
});

test('the share is of the CSRs\' own total, not the workspace\'s', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $top = PancakeUser::create(['name' => 'Angeline Mercado']);
    orderConfirmedBy($workspace, $top, '2026-08-02 10:00:00', 4000);

    // Confirmed by nobody the join can resolve. It counts on the Sales card but
    // belongs to no CSR, so it must not dilute the leader's share.
    confirmedOrder($workspace, '2026-08-02 11:00:00', 4000);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 4000)
        ->assertJsonPath('leader.share', 100);

    // The Sales card still sees the whole workspace — the two differ on purpose.
    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 8000);
});

test('the shares across CSRs add up to the whole', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $top = PancakeUser::create(['name' => 'Angeline Mercado']);
    $other = PancakeUser::create(['name' => 'Someone Else']);

    orderConfirmedBy($workspace, $top, '2026-08-02 10:00:00', 7500);
    orderConfirmedBy($workspace, $other, '2026-08-02 11:00:00', 2500);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.share', 75);
});

test('cancelled orders still count towards a leader, as they do in the table', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 1000);

    // SyncCsrDailyRecord sums every status, so the table credits this too. The
    // leaderboard follows the table rather than the Sales card.
    Order::factory()->forWorkspace($workspace)->create([
        'status' => 6,
        'final_amount' => 500,
        'confirmed_at' => '2026-08-02 11:00:00',
        'confirmed_by' => $csr->id,
    ]);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 1500)
        ->assertJsonPath('leader.orders', 2);
});

test('the leader and the Sales card differ by the cancelled orders', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 1000);

    Order::factory()->forWorkspace($workspace)->create([
        'status' => 6,
        'final_amount' => 500,
        'confirmed_at' => '2026-08-02 11:00:00',
        'confirmed_by' => $csr->id,
    ]);

    // Pinned so the gap stays a known, deliberate one: the leaderboard credits
    // work done, the Sales card reports money kept.
    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 1500);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1000);
});

test('a period with no confirmed orders has no leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});

test('another workspace\'s leader is not borrowed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $stranger = PancakeUser::create(['name' => 'Elsewhere CSR']);
    orderConfirmedBy($other, $stranger, '2026-08-02 10:00:00', 50000);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader', null);
});

test('the sales leader comes off the nightly rollup', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    // No pancake_orders at all — only the row the nightly sync leaves behind.
    PancakeUserPosDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'date' => '2026-08-02',
        'total_orders' => 3,
        'total_sales' => 9000,
    ]);

    csrStat($owner, $workspace, 'analytics-leader-sales', '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader.name', 'Angeline Mercado')
        ->assertJsonPath('leader.value', 9000)
        ->assertJsonPath('leader.orders', 3)
        ->assertJsonPath('leader.aov', 3000);
});

test('a range the rollup has not covered has no leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);
    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 6000);

    // The orders are there and the Sales card reports them, but the sync has
    // not written those days — so there is nobody to crown.
    csrStat($owner, $workspace, 'analytics-leader-sales', '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 6000);
});

test('rollup days outside the range do not count towards the leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 1000);
    orderConfirmedBy($workspace, $csr, '2026-08-09 10:00:00', 9000);

    syncPosRollup('2026-08-01', '2026-08-10');

    csrStat($owner, $workspace, 'analytics-leader-sales', '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 1000)
        ->assertJsonPath('leader.orders', 1);
});

test('a CSR the rollup carries with nothing confirmed cannot lead', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    // What the sync writes for a day whose only activity was a parcel
    // settling: a row, but nothing taken.
    PancakeUserPosDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'date' => '2026-08-02',
        'total_orders' => 0,
        'total_sales' => 0,
        'delivered' => 4000,
    ]);

    csrStat($owner, $workspace, 'analytics-leader-sales', '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});

test('a tie on sales is broken by volume', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $steady = PancakeUser::create(['name' => 'Angeline Mercado']);
    $lucky = PancakeUser::create(['name' => 'Someone Else']);

    // The same money, taken over more orders.
    orderConfirmedBy($workspace, $steady, '2026-08-02 10:00:00', 2000);
    orderConfirmedBy($workspace, $steady, '2026-08-02 11:00:00', 2000);
    orderConfirmedBy($workspace, $steady, '2026-08-02 12:00:00', 2000);
    orderConfirmedBy($workspace, $lucky, '2026-08-02 13:00:00', 6000);

    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Angeline Mercado')
        ->assertJsonPath('leader.orders', 3);
});

function csrRtsLeader($owner, Workspace $workspace, string $from, string $to)
{
    syncPosRollup($from, $to);

    return csrStat($owner, $workspace, 'analytics-leader-rts', $from, $to);
}

/** A parcel credited to $csr that arrived. */
function deliveredBy(Workspace $workspace, PancakeUser $csr, string $at, float $amount): void
{
    Order::factory()->forWorkspace($workspace)->create([
        'status' => 3,
        'final_amount' => $amount,
        'delivered_at' => $at,
        'confirmed_by' => $csr->id,
    ]);
}

/** A parcel credited to $csr that turned back. */
function returnedBy(Workspace $workspace, PancakeUser $csr, string $at, float $amount): void
{
    Order::factory()->forWorkspace($workspace)->create([
        'status' => 4,
        'final_amount' => $amount,
        'returning_at' => $at,
        'confirmed_by' => $csr->id,
    ]);
}

test('the RTS leader is the CSR with the lowest return rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $best = PancakeUser::create(['name' => 'Mariel Bautista']);
    $worst = PancakeUser::create(['name' => 'Someone Else']);

    // Best: 200 returned of 1000 settled = 20%.
    deliveredBy($workspace, $best, '2026-08-02 10:00:00', 800);
    returnedBy($workspace, $best, '2026-08-03 10:00:00', 200);

    // Worst: 600 of 1000 = 60%.
    deliveredBy($workspace, $worst, '2026-08-02 10:00:00', 400);
    returnedBy($workspace, $worst, '2026-08-03 10:00:00', 600);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader.name', 'Mariel Bautista')
        ->assertJsonPath('leader.value', 20)
        ->assertJsonPath('leader.returned', 200)
        ->assertJsonPath('leader.delivered', 800)
        ->assertJsonPath('leader.orders', 2);
});

test('a CSR with nothing settled is not eligible', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $active = PancakeUser::create(['name' => 'Mariel Bautista']);
    $idle = PancakeUser::create(['name' => 'Idle CSR']);

    deliveredBy($workspace, $active, '2026-08-02 10:00:00', 900);
    returnedBy($workspace, $active, '2026-08-03 10:00:00', 100);

    // Confirmed an order, but nothing of theirs settled in the range. A zero
    // rate here would be an absence, not an achievement.
    orderConfirmedBy($workspace, $idle, '2026-08-02 09:00:00', 5000);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Mariel Bautista');
});

test('settlements outside the range do not count', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Mariel Bautista']);

    deliveredBy($workspace, $csr, '2026-08-02 10:00:00', 500);
    returnedBy($workspace, $csr, '2026-08-03 10:00:00', 500);
    // Later, so it must not drag the rate up.
    returnedBy($workspace, $csr, '2026-08-09 10:00:00', 9000);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 50);
});

test('a period where nothing settled has no RTS leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Mariel Bautista']);
    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 1000);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});

test('parcels that settled for nothing are not a rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Mariel Bautista']);

    // Eligibility is money settled, as on the RTS card. 0/0 is no rate at all,
    // so this CSR is out of the ranking rather than sitting in it at 0%.
    deliveredBy($workspace, $csr, '2026-08-02 10:00:00', 0);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});

test('a CSR with a real rate wins over one who settled nothing of value', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $real = PancakeUser::create(['name' => 'Mariel Bautista']);
    $zeroValue = PancakeUser::create(['name' => 'Zero Value CSR']);

    // 100 of 1000 settled = 10%.
    deliveredBy($workspace, $real, '2026-08-02 10:00:00', 900);
    returnedBy($workspace, $real, '2026-08-03 10:00:00', 100);

    // Settled, but worth nothing — no rate, so not in the running at all.
    deliveredBy($workspace, $zeroValue, '2026-08-02 10:00:00', 0);

    csrRtsLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Mariel Bautista')
        ->assertJsonPath('leader.value', 10);
});

function csrRmoCalledLeader($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-leader-rmo-called', $from, $to);
}

/** An RMO delivery on $date assigned to $csr. */
function deliveryFor(Workspace $workspace, PancakeUser $csr, string $date, string $status): void
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
        'assignee_id' => $csr->id,
        'delivery_date' => $date,
    ]);
}

/** A day of RMO rollup figures for $csr, as sync:csr-rmo-daily-records writes them. */
function rmoRollup(
    Workspace $workspace,
    PancakeUser $csr,
    string $date,
    int $called,
    int $confirmed,
    int $callTime = 0,
    int $attempts = 0,
): void {
    // Every reader sums across shops, so which shop these land on is immaterial.
    DB::table('pancake_user_daily_call_reports')->insert([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => 0,
        'date' => $date,
        'total_rmo_assigned_count' => $called,
        'total_rmo_confirmed_count' => $confirmed,
        'total_call_time' => $callTime,
        'total_rmo_call_time' => $callTime,
        'total_called' => $attempts,
        'total_rmo_called' => $attempts,
    ]);
}

test('the RMO leader uses the table\'s RMO % — called over confirmed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $best = PancakeUser::create(['name' => 'Mariel Bautista']);
    $worst = PancakeUser::create(['name' => 'Someone Else']);

    // 30 of 40 = 75%.
    rmoRollup($workspace, $best, '2026-08-02', 20, 25);
    rmoRollup($workspace, $best, '2026-08-03', 10, 15);

    // 10 of 40 = 25%.
    rmoRollup($workspace, $worst, '2026-08-02', 10, 40);

    csrRmoCalledLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader.name', 'Mariel Bautista')
        ->assertJsonPath('leader.value', 75)
        ->assertJsonPath('leader.called', 30)
        ->assertJsonPath('leader.confirmed', 40);
});

test('a CSR who confirmed nothing has no rate and cannot win', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $real = PancakeUser::create(['name' => 'Mariel Bautista']);
    $idle = PancakeUser::create(['name' => 'Idle CSR']);

    rmoRollup($workspace, $real, '2026-08-02', 5, 10);
    // Nothing confirmed: dividing by it would be undefined, not perfect.
    rmoRollup($workspace, $idle, '2026-08-02', 0, 0);

    csrRmoCalledLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Mariel Bautista');
});

test('a tie at the top is broken by volume', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $busy = PancakeUser::create(['name' => 'Mariel Bautista']);
    $quiet = PancakeUser::create(['name' => 'Quiet CSR']);

    // Both perfect, but one did it over more deliveries.
    rmoRollup($workspace, $busy, '2026-08-02', 50, 50);
    rmoRollup($workspace, $quiet, '2026-08-02', 2, 2);

    csrRmoCalledLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Mariel Bautista')
        ->assertJsonPath('leader.value', 100)
        ->assertJsonPath('leader.confirmed', 50);
});

test('rollup days outside the range do not count', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Mariel Bautista']);

    rmoRollup($workspace, $csr, '2026-08-02', 5, 5);
    rmoRollup($workspace, $csr, '2026-08-09', 0, 100);

    csrRmoCalledLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 100)
        ->assertJsonPath('leader.confirmed', 5);
});

test('a period the rollup has not covered has no RMO leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRmoCalledLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});

function csrRmoDurationLeader($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-leader-rmo-duration', $from, $to);
}

test('the duration leader is the CSR with the most talk time, with their average', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $best = PancakeUser::create(['name' => 'Angeline Mercado']);
    $other = PancakeUser::create(['name' => 'Someone Else']);

    // 600s over 5 calls = 120s average.
    rmoRollup($workspace, $best, '2026-08-02', 0, 1, callTime: 400, attempts: 3);
    rmoRollup($workspace, $best, '2026-08-03', 0, 1, callTime: 200, attempts: 2);

    rmoRollup($workspace, $other, '2026-08-02', 0, 1, callTime: 300, attempts: 10);

    csrRmoDurationLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader.name', 'Angeline Mercado')
        ->assertJsonPath('leader.value', 600)
        ->assertJsonPath('leader.calls', 5)
        ->assertJsonPath('leader.average_seconds', 120);
});

test('a CSR with no talk time cannot lead on time spent', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $real = PancakeUser::create(['name' => 'Angeline Mercado']);
    $idle = PancakeUser::create(['name' => 'Idle CSR']);

    rmoRollup($workspace, $real, '2026-08-02', 0, 1, callTime: 60, attempts: 1);
    // Attempts logged, but nobody spoke — 00:00 is not "most time on calls".
    rmoRollup($workspace, $idle, '2026-08-02', 0, 1, callTime: 0, attempts: 40);

    csrRmoDurationLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.name', 'Angeline Mercado');
});

test('talk time with no calls recorded has no average', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    // The two columns are written independently, so this shape is possible.
    rmoRollup($workspace, $csr, '2026-08-02', 0, 1, callTime: 300, attempts: 0);

    csrRmoDurationLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 300)
        ->assertJsonPath('leader.average_seconds', null);
});

test('rollup days outside the range do not add talk time', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    rmoRollup($workspace, $csr, '2026-08-02', 0, 1, callTime: 120, attempts: 2);
    rmoRollup($workspace, $csr, '2026-08-09', 0, 1, callTime: 9999, attempts: 9);

    csrRmoDurationLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 120);
});

test('a period with no talk time has no duration leader', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRmoDurationLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);
});
