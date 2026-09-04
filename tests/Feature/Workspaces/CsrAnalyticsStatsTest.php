<?php

use App\Jobs\SyncCsrDailyRecord;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
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
 * Sales reads pancake_user_pos_daily_reports — the nightly per-CSR rollup the
 * leader, the comparison and the breakdown table below it already read — so
 * the total at the top of the page is the sum of the names under it. The
 * rollup is written nightly, so a range the sync has not reached reads as
 * zero. The remaining cards read the workspace's orders through
 * WorkspaceMetrics.
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
 * A confirmed order belonging to no CSR.
 *
 * `confirmed_at` is the date column the metrics filter on and `final_amount`
 * the figure they sum; statuses 6 and 7 are excluded as cancelled/removed.
 */
function confirmedOrder(Workspace $workspace, string $confirmedAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'final_amount' => $amount,
        'confirmed_at' => $confirmedAt,
    ]);
}

/** The CSR every sale in the Sales card's own tests is credited to. */
function salesCsr(): PancakeUser
{
    return PancakeUser::firstOrCreate(['name' => 'Sales CSR']);
}

/**
 * An order the POS rollup counts as one CSR's sale.
 *
 * `confirmed_by` is what puts it in a rollup row at all — the nightly job
 * joins pancake_users on it, so an order nobody confirmed is in none.
 */
function csrSale(Workspace $workspace, string $confirmedAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'final_amount' => $amount,
        'confirmed_at' => $confirmedAt,
        'confirmed_by' => salesCsr()->id,
    ]);
}

function csrStat($owner, Workspace $workspace, string $stat, string $from, string $to)
{
    syncCallReport($from, $to);

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/{$stat}?from={$from}&to={$to}"
    );
}

function csrStats($owner, Workspace $workspace, string $from, string $to)
{
    // The card reads the nightly POS rollup, so write it for real — over the
    // range and over the equally long stretch before it, which is what the
    // change is measured against.
    $start = CarbonImmutable::parse($from);
    syncPosRollup(
        $start->subDays($start->diffInDays(CarbonImmutable::parse($to)) + 1)->toDateString(),
        $to,
    );

    return csrStat($owner, $workspace, 'analytics-sales', $from, $to);
}

function csrRtsStat($owner, Workspace $workspace, string $from, string $to)
{
    // Same rollup as the Sales card, over the range and the stretch before it.
    $start = CarbonImmutable::parse($from);
    syncPosRollup(
        $start->subDays($start->diffInDays(CarbonImmutable::parse($to)) + 1)->toDateString(),
        $to,
    );

    return csrStat($owner, $workspace, 'analytics-rts', $from, $to);
}

test('sales and orders are summed off the POS daily rollup', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSale($workspace, '2026-08-01 09:00:00', 1000);
    csrSale($workspace, '2026-08-03 14:00:00', 2500);
    // Outside the range on purpose.
    csrSale($workspace, '2026-08-09 10:00:00', 9999);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 3500)
        ->assertJsonPath('orders', 2);
});

test('the whole last day of the range is included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Late on the closing day — a range compared as dates rather than
    // timestamps would drop this.
    csrSale($workspace, '2026-08-05 23:30:00', 800);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 800)
        ->assertJsonPath('orders', 1);
});

test('a cancelled order still counts, as it does for the CSR who confirmed it', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSale($workspace, '2026-08-02 10:00:00', 1000);

    // The rollup credits a CSR with everything they confirmed, whatever became
    // of it later. Excluding this here would put the card below the sum of the
    // leader, the comparison and the table it sits above.
    Order::factory()->forWorkspace($workspace)->create([
        'status' => 6,
        'final_amount' => 5000,
        'confirmed_at' => '2026-08-02 11:00:00',
        'confirmed_by' => salesCsr()->id,
    ]);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 6000)
        ->assertJsonPath('orders', 2);
});

test('an order nobody confirmed is in no CSR row, so the card leaves it out', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSale($workspace, '2026-08-02 10:00:00', 1000);

    Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'final_amount' => 7500,
        'confirmed_at' => '2026-08-02 11:00:00',
        'confirmed_by' => null,
    ]);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1000)
        ->assertJsonPath('orders', 1);
});

test('a range the nightly sync has not reached reads as zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // The orders are there, but the rollup that has not run yet holds no rows
    // for them — the card follows the rollup, so it reports nothing.
    csrSale($workspace, '2026-08-02 10:00:00', 4200);

    expect(PancakeUserPosDailyReport::count())->toBe(0);

    $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/csrs/stats/analytics-sales?from=2026-08-01&to=2026-08-05")
        ->assertJsonPath('value', 0)
        ->assertJsonPath('orders', 0);
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

    csrSale($workspace, '2026-07-28 10:00:00', 2000);
    csrSale($workspace, '2026-08-02 10:00:00', 3000);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3000)
        ->assertJsonPath('previous_value', 2000)
        // Asserted as an int: JSON renders a whole float without its decimal.
        ->assertJsonPath('change', 50);
});

test('a fall reports a negative change', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSale($workspace, '2026-07-28 10:00:00', 4000);
    csrSale($workspace, '2026-08-02 10:00:00', 1000);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('change', -75);
});

test('an empty previous period has no percentage rather than zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrSale($workspace, '2026-08-02 10:00:00', 3000);

    // 0% would read as "flat"; this is the first period with any sales at all.
    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('previous_value', 0)
        ->assertJsonPath('change', null);
});

test('another workspace\'s orders are not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    csrSale($other, '2026-08-02 10:00:00', 7000);

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
 * The one shop the RTS tests' parcels go through.
 *
 * The rollup writes a row — and an rts_rate — per CSR, per shop, per day, so
 * parcels that are meant to share a rate have to share a shop.
 */
function salesShop(Workspace $workspace): Shop
{
    return Shop::firstOrCreate(
        ['workspace_id' => $workspace->id, 'name' => 'Sales Shop'],
    );
}

/**
 * A parcel that came back — the rollup's `returning` money, and the numerator
 * behind its stored rts_rate. Credited to a CSR, since the rollup is keyed by
 * who confirmed it.
 */
function returningOrder(Workspace $workspace, string $returningAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 4,
        'final_amount' => $amount,
        'returning_at' => $returningAt,
        'confirmed_by' => salesCsr()->id,
        'shop_id' => salesShop($workspace)->id,
    ]);
}

/** A parcel that arrived — the rest of that day's denominator. */
function deliveredOrder(Workspace $workspace, string $deliveredAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 3,
        'final_amount' => $amount,
        'delivered_at' => $deliveredAt,
        'confirmed_by' => salesCsr()->id,
        'shop_id' => salesShop($workspace)->id,
    ]);
}

test('the RTS rate is the rollup\'s own rts_rate column', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // One CSR, one shop, one day — so the range is a single rollup row, and
    // the card is that row's stored rate: 2000 / (2000 + 8000) = 20%.
    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-02 14:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('returning_amount', 2000);
});

test('a range of several rows is the mean of their rates, not the rate of the whole', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Aug 2 returned everything it settled and Aug 3 returned none of it, so
    // the two stored rates are 100 and 0 and the card reads 50 — the money
    // says 2000 of 10000, which is 20. Reading the column means the days
    // count equally, however much each settled.
    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-03 10:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 50);
});

test('a day that settled nothing has no rate to average in', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-02 14:00:00', 8000);

    // Confirmed on the 3rd but still in transit: the row is there with a
    // stored rts_rate of 0, and letting that in would halve the card to 10%.
    csrSale($workspace, '2026-08-03 09:00:00', 5000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20);
});

test('the RTS change is reported in percentage points, not a relative move', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Previous period: 10%. Current: 20%. That is +10 points, not +100%.
    returningOrder($workspace, '2026-07-28 10:00:00', 1000);
    deliveredOrder($workspace, '2026-07-28 14:00:00', 9000);

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-02 14:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('previous_value', 10)
        ->assertJsonPath('change', 10);
});

test('a range where nothing settled has no rate rather than a perfect one', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Sales happened, but nothing was delivered or returned yet. A 0% RTS here
    // would read as a flawless period rather than an unfinished one.
    csrSale($workspace, '2026-08-02 10:00:00', 5000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);
});

test('an empty previous period leaves the RTS comparison undefined', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    returningOrder($workspace, '2026-08-02 10:00:00', 2000);
    deliveredOrder($workspace, '2026-08-02 14:00:00', 8000);

    csrRtsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('previous_value', null)
        ->assertJsonPath('change', null);
});

function csrRmoStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-rmo-called', $from, $to);
}

/** The CSR every call in these tests belongs to. */
function rmoAssignee(): PancakeUser
{
    return PancakeUser::firstOrCreate(['name' => 'RMO CSR']);
}

/**
 * A call the nightly report counts in `total_called`.
 *
 * Every call against an order is one, so `$rmo` false gives an order
 * verification call rather than RMO work — the column counts both. A call
 * against no order at all is in no row, and cannot be counted.
 */
function placedCall(Workspace $workspace, string $date, bool $rmo = true): void
{
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => rmoAssignee()->id,
        'phone_number' => '09170000001',
        'call_date' => $date,
        'duration' => 60,
        'order_id' => Order::factory()->forWorkspace($workspace)->create()->id,
        'order_for_delivery_id' => $rmo ? 1 : null,
    ]);
}

test('RMO called is the calls placed across the range', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    placedCall($workspace, '2026-08-02');
    placedCall($workspace, '2026-08-03');
    // Outside the range on purpose.
    placedCall($workspace, '2026-08-09');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 2);
});

test('a verification call counts too — total_called is every call on an order', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    placedCall($workspace, '2026-08-02');
    placedCall($workspace, '2026-08-02', rmo: false);

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 2);
});

test('a call against no order is in no report row', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    placedCall($workspace, '2026-08-02');

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => rmoAssignee()->id,
        'call_date' => '2026-08-02',
        'duration' => 60,
        'order_id' => null,
    ]);

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1);
});

test('the RMO called change is relative, as a count rather than a rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // Two in the previous period, three in this one: +50%.
    placedCall($workspace, '2026-07-28');
    placedCall($workspace, '2026-07-29');

    placedCall($workspace, '2026-08-02');
    placedCall($workspace, '2026-08-03');
    placedCall($workspace, '2026-08-03');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3)
        ->assertJsonPath('previous_value', 2)
        ->assertJsonPath('change', 50);
});

test('a previous period with no calls has no percentage rather than zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    placedCall($workspace, '2026-08-02');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('previous_value', 0)
        ->assertJsonPath('change', null);
});

test('a range with no calls reads zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('change', null);
});

test('another workspace\'s calls are not in the RMO called total', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    placedCall($other, '2026-08-02');

    csrRmoStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0);
});

function csrRmoTimeStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-rmo-time', $from, $to);
}

/**
 * A logged RMO call of $seconds on $date.
 *
 * Carries both an order and a delivery stamp by default. Pass $matched false
 * for one that reached a number belonging to no order at all — the nightly
 * report has no row for it, so no card can count it.
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
        // The delivery stamp is what makes a call RMO work rather than order
        // verification; the order beside it is what names the shop.
        'order_for_delivery_id' => $matched ? 1 : null,
    ]);
}

test('the total called time is every call\'s duration, verification included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // The card reads total_call_time and total_called, so a verification call
    // — an order with no delivery stamp — is in both, unlike the narrower
    // total_rmo_* pair the calls-placed card beside it reads.
    rmoCall($workspace, '2026-08-02', 120);
    placedCall($workspace, '2026-08-02', rmo: false);

    csrRmoTimeStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 180)
        ->assertJsonPath('calls', 2)
        ->assertJsonPath('average_seconds', 90);
});

test('the total called time is the talk time across the range\'s calls', function () {
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

/**
 * A verification call of $seconds on $date.
 *
 * An order with no delivery behind it — the CSR ringing to confirm the order
 * rather than to chase a parcel. That is the split the nightly report makes on
 * order_for_delivery_id, and total_verification_called is its count.
 */
function verificationCall(Workspace $workspace, string $date, int $seconds): void
{
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => rmoAssignee()->id,
        'phone_number' => '09170000001',
        'call_date' => $date,
        'duration' => $seconds,
        'order_id' => Order::factory()->forWorkspace($workspace)->create()->id,
        'order_for_delivery_id' => null,
    ]);
}

test('total verification called counts the calls with no delivery behind them', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 30);
    verificationCall($workspace, '2026-08-02', 5);
    // A one-second call still counts: the CSR rang and was answered.
    verificationCall($workspace, '2026-08-03', 1);
    // Outside the range on purpose.
    verificationCall($workspace, '2026-08-09', 99);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 3);
});

test('an RMO call is not a verification call', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 30);
    // Stamped to a delivery, so the report files it under the RMO columns.
    rmoCall($workspace, '2026-08-02', 60);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1);
});

test('a call against no order is in no report row, verification included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 30);
    // No order at all: the nightly report joins on it, so this is in no row.
    rmoCall($workspace, '2026-08-02', 60, matched: false);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1);
});

test('the verification called change is relative, as a count rather than a rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-07-28', 30);
    verificationCall($workspace, '2026-07-29', 30);

    verificationCall($workspace, '2026-08-02', 30);
    verificationCall($workspace, '2026-08-02', 30);
    verificationCall($workspace, '2026-08-03', 30);

    // 2 to 3 calls is +50%.
    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 3)
        ->assertJsonPath('previous_value', 2)
        ->assertJsonPath('change', 50);
});

test('a range with no verification calls reads zero', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    rmoCall($workspace, '2026-08-02', 60);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('change', null);
});

test('another workspace\'s verification calls are not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    verificationCall($other, '2026-08-02', 30);

    csrCallsPlacedStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0);
});

function csrRealConversationsStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-real-conversations', $from, $to);
}

test('the verification call time is the talk time across those calls', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 120);
    verificationCall($workspace, '2026-08-03', 60);
    // Outside the range on purpose.
    verificationCall($workspace, '2026-08-09', 9999);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('value', 180)
        ->assertJsonPath('calls', 2)
        ->assertJsonPath('average_seconds', 90);
});

test('RMO talk time is not verification talk time', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 30);
    // Stamped to a delivery, so its seconds land in the RMO columns instead.
    rmoCall($workspace, '2026-08-02', 600);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 30)
        ->assertJsonPath('calls', 1);
});

test('the average counts every call, the unanswered ones included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-08-02', 100);
    verificationCall($workspace, '2026-08-02', 50);
    verificationCall($workspace, '2026-08-02', 0);

    // 150 seconds over 3 calls — the one that never joined is still an attempt.
    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('calls', 3)
        ->assertJsonPath('average_seconds', 50);
});

test('the verification time change is relative, not in points', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    verificationCall($workspace, '2026-07-28', 100);
    verificationCall($workspace, '2026-08-02', 150);

    // A duration is a magnitude, so +50% is the readable form.
    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 150)
        ->assertJsonPath('previous_value', 100)
        ->assertJsonPath('change', 50);
});

test('a range with no verification calls has no average and no comparison', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('calls', 0)
        ->assertJsonPath('average_seconds', null)
        ->assertJsonPath('change', null);
});

test('another workspace\'s verification talk time is not counted', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    verificationCall($other, '2026-08-02', 300);

    csrRealConversationsStat($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 0);
});

function csrReachRateStat($owner, Workspace $workspace, string $from, string $to)
{
    return csrStat($owner, $workspace, 'analytics-reach-rate', $from, $to);
}

test('reach rate is real conversations over calls placed', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    // 1 of 4 attempts reached the threshold — 25%.
    rmoCall($workspace, '2026-08-02', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS * 10);
    rmoCall($workspace, '2026-08-02', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);
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

    // The card is the same rollup, so it leaves the stray order out too — a
    // 100% share of a figure smaller than the card's would not add up.
    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 4000);
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

test('the leader and the Sales card agree, cancellations included', function () {
    ['owner' => $owner, 'workspace' => $workspace] = csrStatsContext();

    $csr = PancakeUser::create(['name' => 'Angeline Mercado']);

    orderConfirmedBy($workspace, $csr, '2026-08-02 10:00:00', 1000);

    Order::factory()->forWorkspace($workspace)->create([
        'status' => 6,
        'final_amount' => 500,
        'confirmed_at' => '2026-08-02 11:00:00',
        'confirmed_by' => $csr->id,
    ]);

    // One CSR, so the leader is the whole field — and the card above them is
    // the same rollup summed, cancelled orders and all.
    csrSalesLeader($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('leader.value', 1500);

    csrStats($owner, $workspace, '2026-08-01', '2026-08-05')
        ->assertJsonPath('value', 1500);
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

    // The orders are there, but the sync has not written those days — so there
    // is nobody to crown, and the card above reads zero for the same reason.
    csrStat($owner, $workspace, 'analytics-leader-sales', '2026-08-01', '2026-08-05')
        ->assertOk()
        ->assertJsonPath('leader', null);

    $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/csrs/stats/analytics-sales?from=2026-08-01&to=2026-08-05")
        ->assertJsonPath('value', 0);
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
