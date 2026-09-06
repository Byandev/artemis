<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CallLogPersona;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * "Total called over time" — the stacked call-mix chart.
 *
 * Daily reads the nightly rollup; hourly cannot, because the rollup holds a row
 * per day, so it restates the same three rules against call_logs. These pin the
 * two together: the split is a partition of Total Called, and both granularities
 * count the same calls.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
});

function callMix($owner, Workspace $workspace, string $from, string $to, string $granularity = 'daily', ?string $day = null): array
{
    $url = "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-call-mix?from={$from}&to={$to}&granularity={$granularity}";

    return test()->actingAs($owner)
        ->getJson($day === null ? $url : $url."&day={$day}")
        ->assertOk()
        ->json();
}

/** The bucket whose axis label is $label, or nulls when the chart has no such bucket. */
function mixBucket(array $payload, string $label): array
{
    return collect($payload['buckets'])->firstWhere('label', $label)
        ?? ['customer' => null, 'rider' => null, 'verification' => null];
}

/** A day of the rollup, as sync:csr-daily-call-records writes it. */
function mixRollupDay(Workspace $workspace, string $date, int $customer, int $rider, int $verification, ?int $shopId = null, string $csr = 'Mix CSR'): void
{
    DB::table('pancake_user_daily_call_reports')->insert([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => PancakeUser::firstOrCreate(['name' => $csr])->id,
        'shop_id' => $shopId ?? 0,
        'date' => $date,
        // The three parts are the whole of it, so total_called is their sum.
        'total_called' => $customer + $rider + $verification,
        'total_rmo_called' => $customer + $rider,
        'total_rmo_customer_called' => $customer,
        'total_rmo_rider_called' => $rider,
        'total_verification_called' => $verification,
    ]);
}

/**
 * One logged call at $at, of $persona.
 *
 * A delivery stamp is what makes a call RMO work; without one it is order
 * verification, whatever its persona — the same rule SyncCsrDailyCallRecord
 * applies. The order is only there to name the shop the team filter reads.
 */
function mixCall(Workspace $workspace, string $at, ?string $persona, ?Shop $shop = null, bool $matched = true): void
{
    [$date, $time] = explode(' ', $at);

    $order = $matched
        ? Order::factory()->forWorkspace($workspace)->create($shop ? ['shop_id' => $shop->id] : [])
        : null;

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => PancakeUser::firstOrCreate(['name' => 'Mix CSR'])->id,
        'call_date' => $date,
        'call_time' => $time,
        'duration' => 60,
        'order_id' => $order?->id,
        'persona' => $persona,
        'order_for_delivery_id' => $persona === CallLogPersona::VERIFICATION ? null : 1,
    ]);
}

test('daily buckets split the rollup into the three parts', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 4, rider: 3, verification: 2);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    expect($mix['granularity'])->toBe('daily')
        ->and(mixBucket($mix, 'Aug 2'))
        ->toMatchArray(['customer' => 4, 'rider' => 3, 'verification' => 2]);
});

test('every day in the range is a bucket, quiet ones included', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 1, rider: 0, verification: 0);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    // A gap in the calling has to read as a gap, not as a day the chart skipped.
    expect($mix['buckets'])->toHaveCount(5)
        ->and(collect($mix['buckets'])->pluck('label')->all())
        ->toBe(['Aug 1', 'Aug 2', 'Aug 3', 'Aug 4', 'Aug 5'])
        ->and(mixBucket($mix, 'Aug 4'))
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 0]);
});

test('the three parts add up to the rollup\'s total_called', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 4, rider: 3, verification: 2);
    mixRollupDay($this->workspace, '2026-08-03', customer: 1, rider: 1, verification: 5);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    $stacked = $mix['totals']['customer'] + $mix['totals']['rider'] + $mix['totals']['verification'];

    // The stack's height is the Total Called card, so the chart cannot show a
    // period as busier or quieter than the card above it says it was.
    expect($stacked)->toBe(16)
        ->and((int) DB::table('pancake_user_daily_call_reports')
            ->where('workspace_id', $this->workspace->id)
            ->sum('total_called'))
        ->toBe(16);
});

test('days outside the range are not in the chart', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 2, rider: 0, verification: 0);
    mixRollupDay($this->workspace, '2026-08-09', customer: 99, rider: 99, verification: 99);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    expect($mix['totals'])->toMatchArray(['customer' => 2, 'rider' => 0, 'verification' => 0]);
});

test('another workspace\'s calls are in neither granularity', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();

    mixRollupDay($other, '2026-08-02', customer: 9, rider: 9, verification: 9);
    mixCall($other, '2026-08-02 09:15:00', CallLogPersona::CUSTOMER);

    expect(callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05')['totals'])
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 0])
        ->and(callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly')['totals'])
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 0]);
});

test('hourly returns a bucket for every hour of the day', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::RIDER);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly');

    // Twenty-four bars whether the range is a day or a fortnight, so the chart
    // cannot grow a bar per hour of every day in a long range.
    expect($mix['granularity'])->toBe('hourly')
        ->and($mix['buckets'])->toHaveCount(24)
        ->and(collect($mix['buckets'])->pluck('label')->first())->toBe('00:00')
        ->and(collect($mix['buckets'])->pluck('label')->last())->toBe('23:00');
});

test('hourly puts each call in the hour it was placed', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::CUSTOMER);
    mixCall($this->workspace, '2026-08-02 09:50:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-02 17:05:00', CallLogPersona::VERIFICATION);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly');

    expect(mixBucket($mix, '09:00'))
        ->toMatchArray(['customer' => 1, 'rider' => 1, 'verification' => 0])
        ->and(mixBucket($mix, '17:00'))
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 1])
        ->and(mixBucket($mix, '10:00'))
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 0]);
});

test('the day tab picks which day the hours are read from', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-03 09:40:00', CallLogPersona::CUSTOMER);
    mixCall($this->workspace, '2026-08-03 14:00:00', CallLogPersona::CUSTOMER);

    $second = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly', '2026-08-02');
    $third = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly', '2026-08-03');

    // One day's shape at a time — the other days in the range are not folded in.
    expect($second['day'])->toBe('2026-08-02')
        ->and($second['totals'])->toMatchArray(['customer' => 0, 'rider' => 1])
        ->and(mixBucket($second, '09:00')['rider'])->toBe(1);

    expect($third['day'])->toBe('2026-08-03')
        ->and($third['totals'])->toMatchArray(['customer' => 2, 'rider' => 0])
        ->and(mixBucket($third, '09:00')['customer'])->toBe(1)
        ->and(mixBucket($third, '14:00')['customer'])->toBe(1);
});

test('a day outside the range is ignored rather than obeyed', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-09 09:15:00', CallLogPersona::RIDER);

    // The tab and the date picker can get out of step for a render. Falling
    // back to the whole range answers truthfully; obeying would show a day the
    // page is not displaying.
    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly', '2026-08-09');

    expect($mix['day'])->toBeNull()
        ->and($mix['totals']['rider'])->toBe(1);
});

test('a day is meaningless on a daily reading', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 2, rider: 0, verification: 0);
    mixRollupDay($this->workspace, '2026-08-03', customer: 5, rider: 0, verification: 0);

    // Daily is the whole range by definition, so a stale day left on the query
    // must not quietly narrow the chart to one bar.
    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'daily', '2026-08-02');

    expect($mix['day'])->toBeNull()
        ->and($mix['buckets'])->toHaveCount(5)
        ->and($mix['totals']['customer'])->toBe(7);
});

test('hourly buckets the same hour across every day in the range', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-03 09:40:00', CallLogPersona::RIDER);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly');

    // Hour of day, not hour of each day, so the bucket count is bounded at 24
    // however long the range is. The chart only offers hourly on a single day,
    // but the endpoint must not grow a bar per hour of every day when called
    // directly over a longer one.
    expect(mixBucket($mix, '09:00')['rider'])->toBe(2);
});

test('a call with no delivery behind it is verification, whatever its persona', function () {
    // Persona says who answered; the delivery stamp says what the call was for.
    // A customer call with no delivery is a CSR confirming an order.
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::VERIFICATION);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly');

    expect(mixBucket($mix, '09:00'))
        ->toMatchArray(['customer' => 0, 'rider' => 0, 'verification' => 1]);
});

test('the two granularities count the same calls', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::CUSTOMER);
    mixCall($this->workspace, '2026-08-02 11:00:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-02 11:30:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-02 17:05:00', CallLogPersona::VERIFICATION);

    // The rollup written from those same logs, so daily and hourly are two
    // readings of one day rather than two different figures.
    syncCallReport('2026-08-01', '2026-08-05');

    $daily = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');
    $hourly = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly');

    expect($daily['totals'])->toBe($hourly['totals'])
        ->and($daily['totals'])
        ->toMatchArray(['customer' => 1, 'rider' => 2, 'verification' => 1]);
});

test('the team filter narrows both granularities', function () {
    $team = Team::factory()->create(['workspace_id' => $this->workspace->id]);
    $mine = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $theirs = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $team->shops()->attach($mine->id);

    mixRollupDay($this->workspace, '2026-08-02', customer: 3, rider: 0, verification: 0, shopId: $mine->id);
    mixRollupDay($this->workspace, '2026-08-03', customer: 7, rider: 0, verification: 0, shopId: $theirs->id);

    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::CUSTOMER, $mine);
    mixCall($this->workspace, '2026-08-02 09:45:00', CallLogPersona::CUSTOMER, $theirs);

    $url = "/api/workspaces/{$this->workspace->slug}/csrs/stats/analytics-call-mix?from=2026-08-01&to=2026-08-05&team_id={$team->id}";

    // Both rollups are keyed by shop and the call log reaches one through its
    // order, so the switcher narrows this chart like every other block.
    expect($this->actingAs($this->owner)->getJson($url.'&granularity=daily')->json('totals.customer'))
        ->toBe(3)
        ->and($this->actingAs($this->owner)->getJson($url.'&granularity=hourly')->json('totals.customer'))
        ->toBe(1);
});

test('anything that is not hourly is read as daily', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 2, rider: 0, verification: 0);

    // The granularity arrives from a query string, so it can be absent, a
    // stale value, or nonsense. Daily is the reading that always makes sense.
    foreach (['daily', 'weekly', '', 'hour'] as $granularity) {
        $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', $granularity);

        expect($mix['granularity'])->toBe('daily')
            ->and($mix['buckets'])->toHaveCount(5);
    }

    // Capitalisation is the one variation that should still mean hourly.
    expect(callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'HOURLY')['granularity'])
        ->toBe('hourly');
});

test('a day is one bucket however many shops and CSRs made it', function () {
    $first = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    // The rollup writes a row per CSR, per shop, per day. The chart is the
    // workspace's day, so all of them fold into one bar.
    mixRollupDay($this->workspace, '2026-08-02', customer: 1, rider: 2, verification: 0, shopId: $first->id);
    mixRollupDay($this->workspace, '2026-08-02', customer: 3, rider: 0, verification: 1, shopId: $second->id);
    mixRollupDay($this->workspace, '2026-08-02', customer: 5, rider: 0, verification: 0, shopId: $first->id, csr: 'Another CSR');

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    expect(collect($mix['buckets'])->where('label', 'Aug 2'))->toHaveCount(1)
        ->and(mixBucket($mix, 'Aug 2'))
        ->toMatchArray(['customer' => 9, 'rider' => 2, 'verification' => 1]);
});

test('the totals are the buckets added up', function () {
    mixRollupDay($this->workspace, '2026-08-02', customer: 4, rider: 3, verification: 2);
    mixRollupDay($this->workspace, '2026-08-04', customer: 1, rider: 1, verification: 1);

    $mix = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05');

    // The legend prints these beside each series, so a total that disagreed
    // with the bars would be a figure nothing on the chart supports.
    foreach (['customer', 'rider', 'verification'] as $series) {
        expect($mix['totals'][$series])
            ->toBe((int) collect($mix['buckets'])->sum($series));
    }
});

test('an hourly call matched to no order is left out, as the rollup leaves it out', function () {
    mixCall($this->workspace, '2026-08-02 09:15:00', CallLogPersona::RIDER);
    // A number belonging to no order at all: the rollup has no row to put it
    // in, so hourly must not invent one either.
    mixCall($this->workspace, '2026-08-02 09:30:00', CallLogPersona::RIDER, matched: false);

    expect(callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly')['totals']['rider'])
        ->toBe(1);
});

test('the first and last hours of the day are their own buckets', function () {
    mixCall($this->workspace, '2026-08-02 00:00:00', CallLogPersona::CUSTOMER);
    mixCall($this->workspace, '2026-08-02 23:59:59', CallLogPersona::RIDER);

    $mix = callMix($this->owner, $this->workspace, '2026-08-02', '2026-08-02', 'hourly');

    // Midnight is an hour like any other, and a minute before midnight has not
    // rolled into the next day.
    expect(mixBucket($mix, '00:00')['customer'])->toBe(1)
        ->and(mixBucket($mix, '23:00')['rider'])->toBe(1);
});

test('the next day\'s calls are not in the picked day\'s hours', function () {
    mixCall($this->workspace, '2026-08-02 23:30:00', CallLogPersona::RIDER);
    mixCall($this->workspace, '2026-08-03 00:30:00', CallLogPersona::RIDER);

    $second = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly', '2026-08-02');
    $third = callMix($this->owner, $this->workspace, '2026-08-01', '2026-08-05', 'hourly', '2026-08-03');

    // Half an hour apart, either side of midnight — the tab decides which of
    // the two the chart is showing.
    expect($second['totals']['rider'])->toBe(1)
        ->and(mixBucket($second, '23:00')['rider'])->toBe(1)
        ->and($third['totals']['rider'])->toBe(1)
        ->and(mixBucket($third, '00:00')['rider'])->toBe(1);
});

test('the endpoint needs the CSR analytics permission', function () {
    $outsider = User::factory()->create();
    $this->workspace->users()->attach($outsider->id);

    $this->actingAs($outsider)
        ->getJson("/api/workspaces/{$this->workspace->slug}/csrs/stats/analytics-call-mix?from=2026-08-01&to=2026-08-05")
        ->assertForbidden();
});
