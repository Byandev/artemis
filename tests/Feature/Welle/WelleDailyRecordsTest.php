<?php

use App\Enums\IntegrationService;
use App\Exceptions\WelleException;
use App\Jobs\FetchWelleProgress;
use App\Models\User;
use App\Models\UserIntegration;
use App\Models\WelleDailyRecord;
use App\Services\Welle\WelleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.welle.base_url' => 'https://welle.test']);
});

/**
 * A workspace with the module on, owned by a user holding a Welle token.
 *
 * No password anywhere: it is exchanged for the token at connect time and never
 * stored, so nothing past that point has one to use.
 */
function makeWelleWorkspaceWithConnectedOwner(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['welle_module_enabled' => true]);
    connectWelleAccount($user);

    return ['user' => $user->refresh(), 'workspace' => $workspace];
}

/** A user's Welle connection, freshly read. */
function welleConnection(User $user): ?UserIntegration
{
    return $user->fresh()->integrationFor(IntegrationService::Welle);
}

/** One entry of a progress window, shaped as Welle's ProgressController sends it. */
function welleWeekDay(
    string $date,
    bool $movement = true,
    bool $meditation = true,
    bool $learning = true,
    bool $isFuture = false,
): array {
    return [
        'date' => $date,
        'weekday' => date('D', strtotime($date)),
        'day_of_month' => (int) date('j', strtotime($date)),
        'is_today' => false,
        'is_future' => $isFuture,
        'meditation' => $meditation,
        'learning' => $learning,
        'movement' => $movement,
    ];
}

/**
 * The envelope around those days.
 *
 * @param  array<int, array<string, mixed>>  $days
 */
function welleWeek(array $days, int $streak = 0, int $longest = 0): array
{
    return ['data' => [
        'start' => '2026-09-14',
        'end' => '2026-09-20',
        'streak_days' => $streak,
        'longest_streak' => $longest,
        'days' => $days,
    ]];
}

/**
 * Welle answering both progress endpoints happily.
 *
 * Stubbed per test rather than in beforeEach: Http::fake() merges its stubs and
 * the first match wins, so a default would outrank the failure cases below.
 */
function fakeWelle(?array $window = null): void
{
    $window ??= welleWeek([welleWeekDay('2026-09-15')]);

    Http::fake([
        'welle.test/api/v1/progress/week' => Http::response($window),
        'welle.test/api/v1/progress/range*' => Http::response($window),
    ]);
}

// ── Dispatching ─────────────────────────────────────────────────────────────

test('the command dispatches one job per connected user', function () {
    Queue::fake();

    ['user' => $user, 'workspace' => $workspace] = makeWelleWorkspaceWithConnectedOwner();

    // A member who never connected Welle contributes nothing.
    makeWorkspaceMember($workspace);

    $this->artisan('welle:fetch-daily-records')->assertSuccessful();

    Queue::assertPushed(FetchWelleProgress::class, 1);
    Queue::assertPushed(FetchWelleProgress::class, fn (FetchWelleProgress $job) => $job->userId === $user->id
        && $job->start === null);
});

test('the command skips users whose only welle workspace has the module switched off', function () {
    Queue::fake();

    ['workspace' => $workspace] = makeWelleWorkspaceWithConnectedOwner();
    $workspace->update(['welle_module_enabled' => false]);

    $this->artisan('welle:fetch-daily-records')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('the --user option narrows the run to one account', function () {
    Queue::fake();

    ['user' => $user, 'workspace' => $workspace] = makeWelleWorkspaceWithConnectedOwner();

    $other = makeWorkspaceMember($workspace);
    connectWelleAccount($other, 'other-token');

    $this->artisan('welle:fetch-daily-records', ['--user' => $user->email])->assertSuccessful();

    Queue::assertPushed(FetchWelleProgress::class, 1);
    Queue::assertPushed(FetchWelleProgress::class, fn (FetchWelleProgress $job) => $job->userId === $user->id);
});

test('the command refuses to run without a configured base url', function () {
    config(['services.welle.base_url' => null]);
    Queue::fake();

    $this->artisan('welle:fetch-daily-records')->assertFailed();

    Queue::assertNothingPushed();
});

// ── Windows ─────────────────────────────────────────────────────────────────

test('--days asks for a trailing window ending today, today included', function () {
    Queue::fake();

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    $this->artisan('welle:fetch-daily-records', ['--days' => 30])->assertSuccessful();

    Queue::assertPushed(FetchWelleProgress::class, fn (FetchWelleProgress $job) => $job->userId === $user->id
        && $job->start === now()->subDays(29)->toDateString()
        && $job->end === now()->toDateString());
});

test('--days=1 is today alone', function () {
    Queue::fake();

    makeWelleWorkspaceWithConnectedOwner();

    $this->artisan('welle:fetch-daily-records', ['--days' => 1])->assertSuccessful();

    Queue::assertPushed(FetchWelleProgress::class, fn (FetchWelleProgress $job) => $job->start === now()->toDateString()
        && $job->end === now()->toDateString());
});

test('a windowed job reads the range endpoint, not the week', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-08-20'), welleWeekDay('2026-08-21')]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id, '2026-08-18', '2026-09-16'))->handle(app(WelleClient::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/progress/range')
        && str_contains($request->url(), 'start=2026-08-18')
        && str_contains($request->url(), 'end=2026-09-16'));

    // Days the current week could never have reached.
    expect(WelleDailyRecord::pluck('date')->map->toDateString()->all())
        ->toBe(['2026-08-20', '2026-08-21']);
});

test('a job with no window reads the current week', function () {
    fakeWelle();

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/progress/week'));
});

// ── Fetching ────────────────────────────────────────────────────────────────

test('the job writes the window to every welle-enabled workspace the user belongs to', function () {
    fakeWelle(welleWeek([
        welleWeekDay('2026-09-14'),
        welleWeekDay('2026-09-15'),
    ]));

    ['user' => $user, 'workspace' => $first] = makeWelleWorkspaceWithConnectedOwner();

    // A second workspace the same user belongs to, also on Welle.
    ['workspace' => $second] = makeWorkspaceWithOwner();
    $second->update(['welle_module_enabled' => true]);
    $second->users()->attach($user->id, ['role' => 'member']);

    // ...and a third with the module off, which must not receive the rows.
    ['workspace' => $third] = makeWorkspaceWithOwner();
    $third->users()->attach($user->id, ['role' => 'member']);

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    expect(WelleDailyRecord::count())->toBe(4)
        ->and(WelleDailyRecord::pluck('workspace_id')->unique()->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

test('the job never sends a password, only the stored token', function () {
    fakeWelle();

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    // One request, carrying the token — no login leg at all.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer welle-token-abc'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/login'));
});

test('days that have not happened yet are not stored', function () {
    fakeWelle(welleWeek([
        welleWeekDay('2026-09-14'),
        welleWeekDay('2026-09-15'),
        welleWeekDay('2026-09-16', isFuture: true),
        welleWeekDay('2026-09-17', isFuture: true),
    ]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    // An empty future day in the table would land in the denominator of every
    // rate and read as a missed day.
    expect(WelleDailyRecord::pluck('date')->map->toDateString()->all())
        ->toBe(['2026-09-14', '2026-09-15']);
});

test('a day with all three pillars counts as ESC', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-09-15', movement: true, meditation: true, learning: true)]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    $record = WelleDailyRecord::sole();

    expect($record->pillars_completed)->toBe(3)
        ->and($record->is_esc)->toBeTrue();
});

test('a day missing one pillar is two of three, not ESC', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-09-14', movement: true, meditation: true, learning: false)]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    $record = WelleDailyRecord::sole();

    expect($record->learning)->toBeFalse()
        ->and($record->pillars_completed)->toBe(2)
        ->and($record->is_esc)->toBeFalse();
});

test('an elapsed day with nothing logged is stored as a tracked, empty day', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-09-14', movement: false, meditation: false, learning: false)]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    expect(WelleDailyRecord::sole()->pillars_completed)->toBe(0)
        ->and(WelleDailyRecord::sole()->is_esc)->toBeFalse();
});

test('the window carries the streak figures onto the user', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-09-15')], streak: 4, longest: 12));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    // The streaks are the person's own figures; how the fetch went belongs to
    // the connection that made it.
    expect($user->refresh()->welle_streak_days)->toBe(4)
        ->and($user->welle_longest_streak)->toBe(12);

    expect(welleConnection($user)->last_synced_at)->not->toBeNull()
        ->and(welleConnection($user)->last_error)->toBeNull();
});

test('re-reading the same window corrects the days instead of duplicating them', function () {
    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    Http::fake([
        'welle.test/api/v1/progress/week' => Http::sequence()
            ->push(welleWeek([welleWeekDay('2026-09-15', movement: true, meditation: false, learning: false)]))
            ->push(welleWeek([welleWeekDay('2026-09-15', movement: true, meditation: true, learning: true)])),
    ]);

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));
    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    expect(WelleDailyRecord::count())->toBe(1)
        ->and(WelleDailyRecord::sole()->is_esc)->toBeTrue();
});

test('a rejected token flags the account for reconnection and writes nothing', function () {
    Http::fake(['welle.test/api/v1/progress/week' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    expect(WelleDailyRecord::count())->toBe(0)
        ->and(welleConnection($user)->last_error)->toContain('reconnect')
        ->and(welleConnection($user)->last_synced_at)->toBeNull()
        ->and(welleConnection($user)->needsReconnect())->toBeTrue();
});

test('a user who disconnected between dispatch and run is left alone', function () {
    fakeWelle();

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();
    $user->integrations()->forService(IntegrationService::Welle)->delete();

    (new FetchWelleProgress($user->id))->handle(app(WelleClient::class));

    expect(WelleDailyRecord::count())->toBe(0);
    Http::assertNothingSent();
});

test('a window without a days list bubbles up so the queue retries it', function () {
    Http::fake(['welle.test/api/v1/progress/week' => Http::response(['data' => ['start' => '2026-09-14']])]);

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    expect(fn () => (new FetchWelleProgress($user->id))->handle(app(WelleClient::class)))
        ->toThrow(WelleException::class);

    expect(welleConnection($user)->last_synced_at)->toBeNull();
});

test('an unusable answer from welle bubbles up so the queue retries it', function () {
    Http::fake(['welle.test/api/v1/progress/week' => Http::response('gateway timeout', 504)]);

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    expect(fn () => (new FetchWelleProgress($user->id))->handle(app(WelleClient::class)))
        ->toThrow(WelleException::class);
});

// ── --sync ──────────────────────────────────────────────────────────────────

test('--sync fetches inline so the rows are there when the command returns', function () {
    fakeWelle(welleWeek([welleWeekDay('2026-09-14'), welleWeekDay('2026-09-15')]));

    ['user' => $user] = makeWelleWorkspaceWithConnectedOwner();

    $this->artisan('welle:fetch-daily-records', [
        '--user' => $user->email,
        '--sync' => true,
    ])->assertSuccessful();

    expect(WelleDailyRecord::count())->toBe(2)
        ->and(welleConnection($user)->last_synced_at)->not->toBeNull();
});

test('--sync reports the users that failed and finishes the rest', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWelleWorkspaceWithConnectedOwner();

    $other = makeWorkspaceMember($workspace);
    connectWelleAccount($other, 'other-token');

    Http::fake([
        'welle.test/api/v1/progress/week' => Http::sequence()
            ->push('gateway timeout', 504)
            ->push(welleWeek([welleWeekDay('2026-09-15')])),
    ]);

    $this->artisan('welle:fetch-daily-records', ['--sync' => true])->assertFailed();

    // The failure did not cost the other user their week.
    expect(WelleDailyRecord::count())->toBe(1);
});
