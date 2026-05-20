<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;

test('password update page is displayed', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('password.edit', ['workspace' => $workspace->slug]));

    $response->assertStatus(200);
});

test('password can be updated', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $editUrl = route('password.edit', ['workspace' => $workspace->slug]);

    $response = $this
        ->actingAs($user)
        ->from($editUrl)
        ->put(route('password.update', ['workspace' => $workspace->slug]), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect($editUrl);

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $editUrl = route('password.edit', ['workspace' => $workspace->slug]);

    $response = $this
        ->actingAs($user)
        ->from($editUrl)
        ->put(route('password.update', ['workspace' => $workspace->slug]), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect($editUrl);
});
