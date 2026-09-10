<?php

use App\Enums\Permission;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Effort against results, hour by hour — the chart under the daily one.
 *
 * The same calls the daily chart draws, folded into one round of the clock for
 * the whole range: every day's 9am added into a single 9am. The fold is the
 * endpoint's own GROUP BY HOUR, so twenty-four rows come back however long the
 * range is, and narrowing to a single day is the date range doing it.
 *
 * It reads the call log direct rather than the nightly rollup, which keeps no
 * hour — so no sync runs in these tests, and a range the rollup has never
 * covered still draws.
 */
const HOURLY_FROM = '2026-08-14';
const HOURLY_TO = '2026-08-16';

function hourlyEffort($owner, Workspace $workspace, ?string $from = null, ?string $to = null)
{
    $from ??= HOURLY_FROM;
    $to ??= HOURLY_TO;

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-hourly-effort?from={$from}&to={$to}"
    );
}

/**
 * A logged call of $seconds at $time on $date, RMO work unless told otherwise.
 *
 * The order is what names the shop, so every counted call carries one; the
 * delivery stamp beside it is what makes the call RMO work rather than order
 * verification. A call carrying neither is $ordered: false.
 */
function hourlyCall(
    Workspace $workspace,
    string $date,
    string $time,
    int $seconds,
    bool $rmo = true,
    bool $ordered = true,
    ?Shop $shop = null,
): void {
    $order = Order::factory()->forWorkspace($workspace);

    if ($shop) {
        $order = $order->state(['shop_id' => $shop->id]);
    }

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => PancakeUser::create(['name' => 'Hourly CSR'])->id,
        'phone_number' => '09170000009',
        'call_date' => $date,
        'call_time' => $time,
        'duration' => $seconds,
        'order_id' => $ordered ? $order->create()->id : null,
        'order_for_delivery_id' => $rmo ? 1 : null,
    ]);
}

/** The range's 24 hours, keyed by the hour itself. */
function hoursOf($response): Collection
{
    return collect($response->assertOk()->json('hours'))->keyBy('hour');
}

test('each hour carries the calls placed and the ones that became conversations', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:05:00', 120);
    hourlyCall($workspace, '2026-08-14', '09:47:30', 60);
    hourlyCall($workspace, '2026-08-14', '09:59:59', 0);
    hourlyCall($workspace, '2026-08-14', '14:30:00', 45);

    $hours = hoursOf(hourlyEffort($owner, $workspace));

    expect($hours[9])->toMatchArray(['calls' => 3, 'real' => 2]);
    expect($hours[14])->toMatchArray(['calls' => 1, 'real' => 1]);
});

test('the range comes back as one round of the clock, however long it is', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:05:00', 90);

    $response = hourlyEffort($owner, $workspace)->assertOk();

    // Three days asked for, twenty-four rows back — the grouping is the hour
    // alone, so the response is the same size whatever the range.
    expect($response->json('hours'))->toHaveCount(24);
    expect($response->json('hours.0'))->toMatchArray([
        'hour' => 0,
        'total_calls' => 0,
        'calls' => 0,
        'real' => 0,
    ]);
    expect($response->json('hours.23.hour'))->toBe(23);
});

test('the same hour on different days is added into the one bar', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:15:00', 120);
    hourlyCall($workspace, '2026-08-15', '09:45:00', 120);
    hourlyCall($workspace, '2026-08-15', '09:50:00', 120);

    // A week of mornings, not one Tuesday's: the Friday's 9am and the
    // Saturday's two make a single bar of three.
    expect(hoursOf(hourlyEffort($owner, $workspace))[9])
        ->toMatchArray(['calls' => 3, 'real' => 3]);
});

test('the response says how many days were folded into each hour', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // The bars carry no dates once they are added up, so the count comes back
    // beside them — an hour reading 40 over three days is not 40 in a day.
    expect(hourlyEffort($owner, $workspace)->assertOk()->json('day_count'))->toBe(3);

    expect(hourlyEffort($owner, $workspace, '2026-08-14', '2026-08-14')
        ->assertOk()->json('day_count'))->toBe(1);
});

test('a call outside the range is not counted', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-13', '09:15:00', 120);
    hourlyCall($workspace, '2026-08-17', '09:15:00', 120);

    expect(hourlyEffort($owner, $workspace)->assertOk()->json('totals'))
        ->toMatchArray(['calls' => 0, 'real' => 0]);
});

test('a call too short to be a conversation still counts as effort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall(
        $workspace,
        '2026-08-14',
        '09:15:00',
        RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1,
    );

    expect(hoursOf(hourlyEffort($owner, $workspace))[9])
        ->toMatchArray(['calls' => 1, 'real' => 0]);
});

test('an hour carries the two kinds of call side by side', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '10:05:00', 120);
    hourlyCall($workspace, '2026-08-14', '10:25:00', 0);
    hourlyCall($workspace, '2026-08-14', '10:45:00', 90, rmo: false);
    hourlyCall($workspace, '2026-08-14', '10:55:00', 0, rmo: false);

    expect(hoursOf(hourlyEffort($owner, $workspace))[10])->toMatchArray([
        'calls' => 2,
        'real' => 1,
        'verification_calls' => 2,
        'verification_real' => 1,
    ]);
});

test('a call about no order at all lands on neither side', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '10:05:00', 120, ordered: false);

    // No order means no shop to file the call under, which is the same cut the
    // daily rollup makes — the two charts agree on what is not counted.
    expect(hoursOf(hourlyEffort($owner, $workspace))[10])->toMatchArray([
        'calls' => 0,
        'verification_calls' => 0,
    ]);
});

test('another workspace\'s calls are not counted', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:15:00', 120);
    hourlyCall($other, '2026-08-14', '09:15:00', 120);

    expect(hourlyEffort($owner, $workspace)->assertOk()->json('totals'))
        ->toMatchArray(['calls' => 1, 'real' => 1]);
});

test('the hours narrow to the shops the viewer\'s team can see', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $mine = Shop::factory()->create(['workspace_id' => $workspace->id]);
    $team->shops()->attach($mine->id);
    $theirs = Shop::factory()->create(['workspace_id' => $workspace->id]);

    hourlyCall($workspace, '2026-08-14', '09:15:00', 120, shop: $mine);
    hourlyCall($workspace, '2026-08-14', '09:45:00', 120, shop: $theirs);

    // The analytics permission but not "View All Workspace Data", so the team
    // they are in is the whole of what they can see. The order beside the call
    // is what puts it in a shop, which is where the filter bites.
    $member = makeMemberWithPermissions($workspace, [Permission::ViewCsrAnalytics->value]);
    $team->members()->attach($member->id);

    expect(hoursOf(hourlyEffort($member, $workspace))[9])
        ->toMatchArray(['calls' => 1, 'real' => 1]);
});

test('the range totals are every day added up, matching the daily chart', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:15:00', 120);
    hourlyCall($workspace, '2026-08-15', '13:15:00', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);
    hourlyCall($workspace, '2026-08-16', '17:15:00', 30);
    hourlyCall($workspace, '2026-08-16', '17:45:00', 30, rmo: false);

    $response = hourlyEffort($owner, $workspace)->assertOk();

    // The same four figures the daily chart's totals carry over the same range:
    // only the grouping differs, never the counting.
    expect($response->json('totals'))->toMatchArray([
        'total_calls' => 4,
        'calls' => 3,
        'real' => 2,
        'verification_calls' => 1,
        'verification_real' => 1,
    ]);
    expect($response->json('range'))->toMatchArray([
        'from' => HOURLY_FROM,
        'to' => HOURLY_TO,
    ]);
});

test('an hour carries every call it placed, whichever kind', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    hourlyCall($workspace, '2026-08-14', '09:05:00', 120);
    hourlyCall($workspace, '2026-08-14', '09:40:00', 30, rmo: false);
    // No order behind it, so nothing counts it — the total included.
    hourlyCall($workspace, '2026-08-14', '09:50:00', 90, ordered: false);

    // The all-calls view draws this figure rather than adding the kinds up, so
    // it is counted the way the rollup counts a day.
    expect(hoursOf(hourlyEffort($owner, $workspace))[9])->toMatchArray([
        'total_calls' => 2,
        'calls' => 1,
        'verification_calls' => 1,
    ]);
});

test('the endpoint needs the CSR analytics permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $outsider = User::factory()->create();
    $workspace->users()->attach($outsider->id);

    hourlyEffort($outsider, $workspace)->assertForbidden();
});
