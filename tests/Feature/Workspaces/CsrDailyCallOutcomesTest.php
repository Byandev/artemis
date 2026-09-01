<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The day-by-day call outcomes table under the effort chart.
 *
 * Its own endpoint, not more columns on the chart's: the two answer different
 * questions, and the table carries a split the chart never draws. Same source
 * and rules as the call cards — RMO calls only, meaning a call carrying an
 * order_id, and a conversation at the shared five-second threshold — so a row
 * here and a card above it always agree.
 */
const OUTCOMES_FROM = '2026-08-14';
const OUTCOMES_TO = '2026-08-16';

function callOutcomes($owner, Workspace $workspace, ?string $from = null, ?string $to = null)
{
    $from ??= OUTCOMES_FROM;
    $to ??= OUTCOMES_TO;

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-daily-call-outcomes?from={$from}&to={$to}"
    );
}

/** A logged call of $seconds on $date; matched to an order unless told otherwise. */
function outcomeCall(Workspace $workspace, string $date, int $seconds, bool $matched = true): void
{
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => PancakeUser::create(['name' => 'Outcomes CSR'])->id,
        'phone_number' => '09170000008',
        'call_date' => $date,
        'duration' => $seconds,
        'order_id' => $matched
            ? Order::factory()->forWorkspace($workspace)->create()->id
            : null,
    ]);
}

/** The row for one date, out of the run of days the endpoint returns. */
function outcomeDay($response, string $date): array
{
    return collect($response->json('days'))->firstWhere('date', $date);
}

test('a day splits into never answered, answered and conversations', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    outcomeCall($workspace, '2026-08-14', 0);
    outcomeCall($workspace, '2026-08-14', 0);
    // Picked up, then hung up before it was a conversation: answered, but not
    // a conversation — the gap the three columns leave between them.
    outcomeCall($workspace, '2026-08-14', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);
    outcomeCall($workspace, '2026-08-14', 90);

    expect(outcomeDay(callOutcomes($owner, $workspace)->assertOk(), '2026-08-14'))
        ->toMatchArray([
            'calls' => 4,
            'no_answer' => 2,
            'answered' => 2,
            'conversations' => 1,
        ]);
});

test('the hit rate is conversations over every attempt, as the reach card reports', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach (range(1, 8) as $i) {
        outcomeCall($workspace, '2026-08-14', $i <= 1 ? 120 : 0);
    }

    // One conversation out of eight attempts — not one out of the one that was
    // answered, which would flatter the day into 100%.
    expect(outcomeDay(callOutcomes($owner, $workspace)->assertOk(), '2026-08-14'))
        ->toMatchArray(['calls' => 8, 'conversations' => 1, 'hit_rate' => 12.5]);
});

test('a day with no calls has no hit rate rather than a zero one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    outcomeCall($workspace, '2026-08-14', 90);

    $response = callOutcomes($owner, $workspace)->assertOk();

    // Every day in the range gets a row, quiet ones included.
    expect($response->json('days'))->toHaveCount(3);
    expect(outcomeDay($response, '2026-08-15'))->toMatchArray([
        'calls' => 0,
        'no_answer' => 0,
        'conversations' => 0,
        'hit_rate' => null,
    ]);
});

test('calls belonging to no delivery are not RMO calls', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    outcomeCall($workspace, '2026-08-14', 120, matched: false);

    expect(outcomeDay(callOutcomes($owner, $workspace)->assertOk(), '2026-08-14'))
        ->toMatchArray(['calls' => 0, 'conversations' => 0, 'hit_rate' => null]);
});

test('another workspace\'s calls are not counted', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    outcomeCall($workspace, '2026-08-14', 120);
    outcomeCall($other, '2026-08-14', 120);

    expect(callOutcomes($owner, $workspace)->assertOk()->json('totals'))
        ->toMatchArray(['calls' => 1, 'conversations' => 1]);
});

test('the period hit rate is its own rate, not the mean of the daily ones', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // A busy day that reached nobody, then a one-call day that connected.
    // Averaging the two daily rates would call the period 50%; weighing them by
    // the calls behind them makes it 1 in 10.
    foreach (range(1, 9) as $i) {
        outcomeCall($workspace, '2026-08-14', 0);
    }
    outcomeCall($workspace, '2026-08-15', 120);

    expect(callOutcomes($owner, $workspace)->assertOk()->json('totals'))
        ->toMatchArray([
            'calls' => 10,
            'no_answer' => 9,
            'answered' => 1,
            'conversations' => 1,
            'hit_rate' => 10.0,
        ]);
});

test('the endpoint needs the CSR analytics permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $outsider = User::factory()->create();
    $workspace->users()->attach($outsider->id);

    callOutcomes($outsider, $workspace)->assertForbidden();
});
