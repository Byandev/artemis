<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.welle.base_url' => 'https://welle.test']);

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
function connectWelle(User $user, string $token = 'welle-token-abc'): void
{
    $user->forceFill(['welle_email' => 'integration@example.com', 'welle_token' => $token])->save();
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

    $user = $this->owner->fresh();

    expect($user->welle_email)->toBe('integration@example.com')
        ->and($user->welle_token)->toBe('welle-token-abc')
        ->and($user->hasWelleToken())->toBeTrue();

    // The password was sent to Welle and dropped — the users table has no
    // column for it at all, and nothing about the request left one behind.
    expect(Schema::hasColumn('users', 'welle_password'))->toBeFalse();

    $stored = (array) DB::table('users')->where('id', $user->id)->first();
    expect(collect($stored)->filter(fn ($value) => $value === 'welle-secret'))->toBeEmpty();

    // The token column holds ciphertext, not the token itself.
    expect($stored['welle_token'])->not->toBe('welle-token-abc');
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

    expect($this->owner->fresh()->hasWelleToken())->toBeFalse();
});

it('blames welle, not the password, when welle cannot be reached', function () {
    Http::fake(['welle.test/api/v1/login' => Http::response('bad gateway', 502)]);

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasErrors('welle_email');

    expect($this->owner->fresh()->hasWelleToken())->toBeFalse();
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

    expect($this->owner->fresh()->welle_email)->toBe('integration@example.com')
        ->and($member->fresh()->welle_email)->toBe('member@example.com');
});

it('never serializes the welle token to the client', function () {
    connectWelle($this->owner);

    expect($this->owner->fresh()->toArray())->not->toHaveKey('welle_token');
});

it('reports the connection state to the page without leaking the token', function () {
    connectWelle($this->owner);

    $this->actingAs($this->owner)
        ->get($this->editUrl)
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations')
            ->where('welle.email', 'integration@example.com')
            ->where('welle.connected', true)
            ->missing('welle.token')
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
    connectWelle($this->owner);
    $this->owner->forceFill([
        'welle_last_synced_at' => now()->subHours(3),
        'welle_last_error' => 'Welle rejected your token — reconnect your account.',
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

    connectWelle($this->owner, 'stale-token');
    $this->owner->forceFill(['welle_last_error' => 'Welle rejected your token.'])->save();

    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors();

    $user = $this->owner->fresh();

    expect($user->welle_token)->toBe('welle-token-abc')
        ->and($user->welle_last_error)->toBeNull();
});

it('leaves a stale failure in place when the exchange fails', function () {
    connectWelle($this->owner, 'stale-token');
    $this->owner->forceFill(['welle_last_error' => 'Welle rejected your token.'])->save();

    Http::fake(['welle.test/api/v1/login' => Http::response(['message' => 'nope'], 422)]);

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload);

    // Nothing has been fixed, so the page must keep saying so.
    expect($this->owner->fresh()->welle_last_error)->not->toBeNull();
});

it('disconnects the welle account', function () {
    connectWelle($this->owner);
    $this->owner->forceFill([
        'welle_last_synced_at' => now(),
        'welle_last_error' => 'Welle rejected your token.',
    ])->save();

    $this->actingAs($this->owner)
        ->delete($this->destroyUrl)
        ->assertRedirect($this->editUrl);

    $user = $this->owner->fresh();

    expect($user->welle_email)->toBeNull()
        ->and($user->welle_token)->toBeNull()
        ->and($user->hasWelleToken())->toBeFalse()
        ->and($user->welle_last_synced_at)->toBeNull()
        ->and($user->welle_last_error)->toBeNull();
});
