<?php

use App\Models\User;
use App\Models\Workspace;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['workspace' => $workspace->slug]));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $editUrl = route('profile.edit', ['workspace' => $workspace->slug]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update', ['workspace' => $workspace->slug]), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect($editUrl);

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update', ['workspace' => $workspace->slug]), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['workspace' => $workspace->slug]));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy', ['workspace' => $workspace->slug]), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $editUrl = route('profile.edit', ['workspace' => $workspace->slug]);

    $response = $this
        ->actingAs($user)
        ->from($editUrl)
        ->delete(route('profile.destroy', ['workspace' => $workspace->slug]), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect($editUrl);

    expect($user->fresh())->not->toBeNull();
});
