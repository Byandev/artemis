<?php

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
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

it('connects a welle account to the signed-in user and stores the password encrypted', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->editUrl);

    $user = $this->owner->fresh();

    expect($user->welle_email)->toBe('integration@example.com')
        ->and($user->welle_password)->toBe('welle-secret')
        ->and($user->hasWellePassword())->toBeTrue();

    // The stored column is ciphertext, not the plaintext password.
    expect(DB::table('users')->where('id', $user->id)->value('welle_password'))
        ->not->toBe('welle-secret');

    // Credentials live on the user, so the workspace record is untouched.
    expect($this->workspace->fresh()->getAttributes())
        ->not->toHaveKey('welle_email');
});

it('keeps each member\'s welle account separate', function () {
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

it('never serializes the welle password to the client', function () {
    $this->owner->update($this->payload);

    expect($this->owner->fresh()->toArray())->not->toHaveKey('welle_password');
});

it('reports the connection state to the page without leaking the password', function () {
    $this->owner->update($this->payload);

    $this->actingAs($this->owner)
        ->get($this->editUrl)
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations')
            ->where('welle.email', 'integration@example.com')
            ->where('welle.connected', true)
            ->missing('welle.password')
        );
});

it('requires a password on the first connect', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, ['welle_email' => 'integration@example.com'])
        ->assertSessionHasErrors('welle_password');
});

it('keeps the stored password when the field is left blank', function () {
    $this->owner->update($this->payload);

    $this->actingAs($this->owner)
        ->put($this->updateUrl, ['welle_email' => 'someone-else@example.com'])
        ->assertSessionHasNoErrors();

    $user = $this->owner->fresh();

    expect($user->welle_email)->toBe('someone-else@example.com')
        ->and($user->welle_password)->toBe('welle-secret');
});

it('rejects an email that is not an email', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, ['welle_email' => 'not-an-email', 'welle_password' => 'welle-secret'])
        ->assertSessionHasErrors('welle_email');
});

it('disconnects the welle account', function () {
    $this->owner->update($this->payload);

    $this->actingAs($this->owner)
        ->delete($this->destroyUrl)
        ->assertRedirect($this->editUrl);

    $user = $this->owner->fresh();

    expect($user->welle_email)->toBeNull()
        ->and($user->welle_password)->toBeNull()
        ->and($user->hasWellePassword())->toBeFalse();
});
