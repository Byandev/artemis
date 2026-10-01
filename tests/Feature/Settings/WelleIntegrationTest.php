<?php

use App\Enums\IntegrationService;
use App\Jobs\FetchWelleProgress;
use App\Models\User;
use App\Models\UserIntegration;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.welle.base_url' => 'https://welle.test']);

    // Connecting now queues a backfill. The queue is synchronous under test, so
    // without this every connect below would fetch two months inline — turning
    // assertions about the one login call into assertions about three requests.
    Bus::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->owner->id,
        'welle_module_enabled' => true,
    ]);

    $this->editUrl = route('integrations.edit', ['workspace' => $this->workspace->slug]);
    $this->updateUrl = route('integrations.welle.update', ['workspace' => $this->workspace->slug]);
    $this->destroyUrl = route('integrations.welle.destroy', ['workspace' => $this->workspace->slug]);
    $this->payload = [
        'welle_email' => 'integration@example.com',
        'welle_password' => 'welle-secret',
    ];
});

/**
 * Welle accepting the exchange.
 *
 * Called per test rather than in beforeEach: Http::fake() merges its stubs and
 * the first match wins, so a default here would quietly outrank the failure
 * cases below.
 */
function fakeWelleLogin(): void
{
    Http::fake([
        'welle.test/api/v1/login' => Http::response(['user' => ['id' => 1], 'token' => 'welle-token-abc']),
    ]);
}

/** Connect the owner without going through the form. */
function connectWelle(User $user, string $token = 'welle-token-abc'): UserIntegration
{
    return $user->integrations()->updateOrCreate(
        ['service' => IntegrationService::Welle],
        ['token' => $token],
    );
}

/** The owner's Welle connection, freshly read. */
function welleIntegration(User $user): ?UserIntegration
{
    return $user->fresh()->integrationFor(IntegrationService::Welle);
}

it('shows the integrations page to a workspace member', function () {
    $this->actingAs($this->owner)->get($this->editUrl)->assertOk();
});

it('404s when the welle module is switched off for the workspace', function () {
    $this->workspace->update(['welle_module_enabled' => false]);

    $this->actingAs($this->owner)->get($this->editUrl)->assertNotFound();
    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload)->assertNotFound();
});

it('forbids a non-member from reading or writing the integration', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get($this->editUrl)->assertForbidden();
    $this->actingAs($stranger)->put($this->updateUrl, $this->payload)->assertForbidden();
});

it('exchanges the credentials for a token and stores only the token', function () {
    fakeWelleLogin();

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->editUrl);

    $integration = welleIntegration($this->owner);

    expect($integration)->not->toBeNull()
        ->and($integration->service)->toBe(IntegrationService::Welle)
        ->and($integration->token)->toBe('welle-token-abc')
        ->and($integration->hasToken())->toBeTrue();

    // Nothing that could be used to sign in as them is kept: no password
    // column on users at all, and no address on the integration row either.
    expect(Schema::hasColumn('users', 'welle_password'))->toBeFalse()
        ->and(Schema::hasColumn('user_integrations', 'username'))->toBeFalse()
        ->and(Schema::hasColumn('user_integrations', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('user_integrations', 'email'))->toBeFalse();

    $stored = (array) DB::table('user_integrations')->where('id', $integration->id)->first();

    // Neither the password nor the plaintext token is anywhere in the row.
    expect(collect($stored)->filter(fn ($value) => in_array($value, ['welle-secret', 'integration@example.com'], true)))
        ->toBeEmpty()
        ->and($stored['token'])->not->toBe('welle-token-abc');
});

it('sends the password to welle exactly once, on connect', function () {
    fakeWelleLogin();

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/login')
        && $request['email'] === 'integration@example.com'
        && $request['password'] === 'welle-secret'
        && filled($request['device_name']));
});

it('rejects credentials welle will not accept, saving nothing', function () {
    Http::fake([
        'welle.test/api/v1/login' => Http::response([
            'message' => 'These credentials do not match our records.',
        ], 422),
    ]);

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasErrors('welle_password');

    expect(welleIntegration($this->owner))->toBeNull();
});

it('blames welle, not the password, when welle cannot be reached', function () {
    Http::fake(['welle.test/api/v1/login' => Http::response('bad gateway', 502)]);

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasErrors('welle_email');

    expect(welleIntegration($this->owner))->toBeNull();
});

it('keeps each member\'s welle account separate', function () {
    fakeWelleLogin();

    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);
    $this->actingAs($member)->put($this->updateUrl, [
        'welle_email' => 'member@example.com',
        'welle_password' => 'member-secret',
    ]);

    // Two connections, one each, neither able to read the other's token.
    expect(welleIntegration($this->owner)->token)->toBe('welle-token-abc')
        ->and(welleIntegration($member)->token)->toBe('welle-token-abc')
        ->and(welleIntegration($this->owner)->id)->not->toBe(welleIntegration($member)->id);
});

it('never serializes the welle token to the client', function () {
    $integration = connectWelle($this->owner);

    expect($integration->fresh()->toArray())->not->toHaveKey('token')
        ->and($this->owner->fresh()->toArray())->not->toHaveKey('welle_token');
});

it('reports the connection state to the page without leaking the token', function () {
    connectWelle($this->owner);

    $this->actingAs($this->owner)
        ->get($this->editUrl)
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations')
            ->where('welle.connected', true)
            ->missing('welle.token')
            // Not even the address reaches the page — it is not stored.
            ->missing('welle.email')
        );
});

it('requires a password every time, since none is kept to fall back on', function () {
    connectWelle($this->owner);

    $this->actingAs($this->owner)
        ->put($this->updateUrl, ['welle_email' => 'integration@example.com'])
        ->assertSessionHasErrors('welle_password');
});

it('rejects an email that is not an email', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, ['welle_email' => 'not-an-email', 'welle_password' => 'welle-secret'])
        ->assertSessionHasErrors('welle_email');
});

it('shows the page how the last unattended fetch went', function () {
    connectWelle($this->owner)->forceFill([
        'last_synced_at' => now()->subHours(3),
        'last_error' => 'Welle rejected your token — reconnect your account.',
    ])->save();

    $this->actingAs($this->owner)
        ->get($this->editUrl)
        ->assertInertia(fn ($page) => $page
            ->where('welle.last_error', 'Welle rejected your token — reconnect your account.')
            ->has('welle.last_synced_at')
        );
});

it('replaces the token and clears a stale failure when reconnecting', function () {
    fakeWelleLogin();

    connectWelle($this->owner, 'stale-token')
        ->forceFill(['last_error' => 'Welle rejected your token.'])->save();

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors();

    $integration = welleIntegration($this->owner);

    // Replaced in place, not stacked up beside the old one.
    expect(UserIntegration::count())->toBe(1)
        ->and($integration->token)->toBe('welle-token-abc')
        ->and($integration->last_error)->toBeNull();
});

it('leaves a stale failure in place when the exchange fails', function () {
    connectWelle($this->owner, 'stale-token')
        ->forceFill(['last_error' => 'Welle rejected your token.'])->save();

    Http::fake(['welle.test/api/v1/login' => Http::response(['message' => 'nope'], 422)]);

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    // Nothing has been fixed, so the page must keep saying so.
    expect(welleIntegration($this->owner)->last_error)->not->toBeNull();
});

it('disconnects the welle account', function () {
    connectWelle($this->owner)->forceFill([
        'last_synced_at' => now(),
        'last_error' => 'Welle rejected your token.',
    ])->save();

    $this->actingAs($this->owner)
        ->delete($this->destroyUrl)
        ->assertRedirect($this->editUrl);

    // The row goes, taking the token and its sync state with it.
    expect(welleIntegration($this->owner))->toBeNull()
        ->and(UserIntegration::count())->toBe(0);
});

// ── Backfill on connect ─────────────────────────────────────────────────────

it('fills in the last two calendar months for the user who connected', function () {
    freezeWelleToday('2026-09-16 09:00:00');
    fakeWelleLogin();

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors();

    // One job per month, oldest first: August whole, September only as far as
    // today — the rest of this month has not happened yet.
    Bus::assertChained([
        fn (FetchWelleProgress $job) => $job->userId === $this->owner->id
            && $job->start === '2026-08-01'
            && $job->end === '2026-08-31',
        fn (FetchWelleProgress $job) => $job->userId === $this->owner->id
            && $job->start === '2026-09-01'
            && $job->end === '2026-09-16',
    ]);
});

it('backfills only the account that was just connected', function () {
    fakeWelleLogin();

    $member = makeWorkspaceMember($this->workspace);
    connectWelleAccount($member, 'member-token');

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    // The member has a connected account of their own, and no part of this
    // request is entitled to read it — they are reached by the nightly command.
    Bus::assertDispatchedTimes(FetchWelleProgress::class, 1);
    Bus::assertDispatched(FetchWelleProgress::class, fn (FetchWelleProgress $job) => $job->userId === $this->owner->id);
});

it('queues no backfill when the exchange fails', function () {
    Http::fake(['welle.test/api/v1/login' => Http::response(['message' => 'nope'], 422)]);

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    // No token was stored, so there is nothing to fetch with.
    Bus::assertNothingDispatched();
});

it('backfills again on a reconnect, closing the gap a dead token left', function () {
    fakeWelleLogin();

    connectWelleAccount($this->owner, 'stale-token')
        ->forceFill(['last_error' => 'Welle rejected your token.'])->save();

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    // Re-reading a window corrects its days rather than duplicating them, so
    // repeating the fetch costs a couple of calls and no consistency.
    Bus::assertDispatchedTimes(FetchWelleProgress::class, 1);
});
