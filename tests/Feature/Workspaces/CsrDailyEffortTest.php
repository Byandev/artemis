<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Effort against results, day by day — the chart under the CSR breakdown.
 *
 * It is the calls-placed and real-conversations cards spread across the days
 * that made them, so it follows those cards exactly: RMO calls only (a call
 * carrying an order_id), and a conversation is one that lasted the shared
 * five-second threshold. A day here and the card above it cannot disagree.
 */
const EFFORT_FROM = '2026-08-14';
const EFFORT_TO = '2026-08-16';

function dailyEffort($owner, Workspace $workspace, ?string $from = null, ?string $to = null)
{
    $from ??= EFFORT_FROM;
    $to ??= EFFORT_TO;

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-daily-effort?from={$from}&to={$to}"
    );
}

/** A logged call of $seconds on $date; matched to an order unless told otherwise. */
function effortCall(Workspace $workspace, string $date, int $seconds, bool $matched = true): void
{
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => PancakeUser::create(['name' => 'Effort CSR'])->id,
        'phone_number' => '09170000009',
        'call_date' => $date,
        'duration' => $seconds,
        'order_id' => $matched
            ? Order::factory()->forWorkspace($workspace)->create()->id
            : null,
    ]);
}

test('each day carries the calls placed and the ones that became conversations', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120);
    effortCall($workspace, '2026-08-14', 60);
    effortCall($workspace, '2026-08-14', 0);
    effortCall($workspace, '2026-08-16', 45);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray(['calls' => 3, 'real' => 2]);
    expect($days['2026-08-16'])->toMatchArray(['calls' => 1, 'real' => 1]);
});

test('a day nobody worked is returned as a zero rather than dropped', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 90);

    $days = dailyEffort($owner, $workspace)->assertOk()->json('days');

    // Three days asked for, three days back — the quiet middle day included, so
    // the run of bars keeps its gap instead of closing up.
    expect($days)->toHaveCount(3);
    expect($days[1])->toMatchArray(['date' => '2026-08-15', 'calls' => 0, 'real' => 0]);
});

test('a call too short to be a conversation still counts as effort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray(['calls' => 1, 'real' => 0]);
});

test('calls belonging to no delivery are not RMO effort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120, matched: false);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray(['calls' => 0, 'real' => 0]);
});

test('another workspace\'s calls are not counted', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120);
    effortCall($other, '2026-08-14', 120);

    $response = dailyEffort($owner, $workspace)->assertOk();

    expect($response->json('totals'))->toMatchArray(['calls' => 1, 'real' => 1]);
});

test('the totals are the days added up, matching the cards above', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120);
    effortCall($workspace, '2026-08-15', 3);
    effortCall($workspace, '2026-08-16', 30);

    $response = dailyEffort($owner, $workspace)->assertOk();

    expect($response->json('totals'))->toMatchArray(['calls' => 3, 'real' => 2]);
    expect($response->json('range'))->toMatchArray([
        'from' => EFFORT_FROM,
        'to' => EFFORT_TO,
    ]);
});

test('the endpoint needs the CSR analytics permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $outsider = User::factory()->create();
    $workspace->users()->attach($outsider->id);

    dailyEffort($outsider, $workspace)->assertForbidden();
});
