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
 * that made them, so it follows those cards exactly: a conversation is one that
 * lasted the shared five-second threshold. A day here and the card above it
 * cannot disagree.
 *
 * Every day carries both kinds of call — RMO (stamped to a delivery) and order
 * verification (not) — so the chart can stack the pair or draw the RMO half on
 * its own without a second request.
 */
const EFFORT_FROM = '2026-08-14';
const EFFORT_TO = '2026-08-16';

function dailyEffort($owner, Workspace $workspace, ?string $from = null, ?string $to = null)
{
    $from ??= EFFORT_FROM;
    $to ??= EFFORT_TO;

    syncCallReport($from, $to);

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-daily-effort?from={$from}&to={$to}"
    );
}

/**
 * A logged call of $seconds on $date, RMO work unless told otherwise.
 *
 * The order is what names the shop, so every counted call carries one; the
 * delivery stamp beside it is what makes the call RMO work rather than order
 * verification. A call carrying neither is $ordered: false — the rollup has no
 * row to file it under.
 */
function effortCall(
    Workspace $workspace,
    string $date,
    int $seconds,
    bool $rmo = true,
    bool $ordered = true,
): void {
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => PancakeUser::create(['name' => 'Effort CSR'])->id,
        'phone_number' => '09170000009',
        'call_date' => $date,
        'duration' => $seconds,
        'order_id' => $ordered
            ? Order::factory()->forWorkspace($workspace)->create()->id
            : null,
        'order_for_delivery_id' => $rmo ? 1 : null,
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
    expect($days[1])->toMatchArray([
        'date' => '2026-08-15',
        'total_calls' => 0,
        'calls' => 0,
        'real' => 0,
        'verification_calls' => 0,
        'verification_real' => 0,
    ]);
});

test('a call too short to be a conversation still counts as effort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray(['calls' => 1, 'real' => 0]);
});

test('a call belonging to no delivery is verification effort, not RMO', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120, rmo: false);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    // The RMO pair stays clean — the split is what keeps the two stackable.
    expect($days['2026-08-14'])->toMatchArray([
        'calls' => 0,
        'real' => 0,
        'verification_calls' => 1,
        'verification_real' => 1,
    ]);
});

test('a call about no order at all lands on neither side', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120, ordered: false);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    // No order means no shop to file the call under, and shop is part of the
    // rollup's key — so the day stays empty rather than growing a bar.
    expect($days['2026-08-14'])->toMatchArray([
        'calls' => 0,
        'real' => 0,
        'verification_calls' => 0,
        'verification_real' => 0,
    ]);
});

test('a day carries the two kinds of call side by side', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120);
    effortCall($workspace, '2026-08-14', 60);
    effortCall($workspace, '2026-08-14', 0);
    effortCall($workspace, '2026-08-14', 90, rmo: false);
    effortCall($workspace, '2026-08-14', 45, rmo: false);
    effortCall($workspace, '2026-08-14', 0, rmo: false);

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray([
        'calls' => 3,
        'real' => 2,
        'verification_calls' => 3,
        'verification_real' => 2,
    ]);
});

test('a verification call too short to be a conversation still counts as effort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall(
        $workspace,
        '2026-08-14',
        RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1,
        rmo: false,
    );

    $days = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->keyBy('date');

    expect($days['2026-08-14'])->toMatchArray([
        'verification_calls' => 1,
        'verification_real' => 0,
    ]);
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
    // Under the threshold, so it is effort without a conversation.
    effortCall($workspace, '2026-08-15', RmoDailyStats::CONNECTED_CALL_MIN_SECONDS - 1);
    effortCall($workspace, '2026-08-16', 30);
    effortCall($workspace, '2026-08-16', 30, rmo: false);

    $response = dailyEffort($owner, $workspace)->assertOk();

    expect($response->json('totals'))->toMatchArray([
        'total_calls' => 4,
        'calls' => 3,
        'real' => 2,
        'verification_calls' => 1,
        'verification_real' => 1,
    ]);
    expect($response->json('range'))->toMatchArray([
        'from' => EFFORT_FROM,
        'to' => EFFORT_TO,
    ]);
});

test('a day carries the rollup\'s own total of every call placed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    effortCall($workspace, '2026-08-14', 120);
    effortCall($workspace, '2026-08-14', 30, rmo: false);
    // No order behind it, so the rollup has no row to file it under and the
    // total cannot see it either.
    effortCall($workspace, '2026-08-14', 90, ordered: false);

    $day = collect(dailyEffort($owner, $workspace)->assertOk()->json('days'))
        ->firstWhere('date', '2026-08-14');

    // total_called off the rollup, which is the two kinds it also reports — the
    // all-calls view reads the recorded figure rather than adding them up.
    expect($day)->toMatchArray([
        'total_calls' => 2,
        'calls' => 1,
        'verification_calls' => 1,
    ]);
});

test('the endpoint needs the CSR analytics permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $outsider = User::factory()->create();
    $workspace->users()->attach($outsider->id);

    dailyEffort($outsider, $workspace)->assertForbidden();
});
