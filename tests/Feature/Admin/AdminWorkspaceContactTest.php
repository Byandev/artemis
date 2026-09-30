<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

it('lets a super admin save the messenger link and last interaction date', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.workspaces.update-contact', $workspace), [
            'messenger_link' => 'https://m.me/acme',
            'last_interaction_at' => Carbon::yesterday()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $workspace->refresh();

    expect($workspace->messenger_link)->toBe('https://m.me/acme')
        ->and($workspace->last_interaction_at->toDateString())->toBe(Carbon::yesterday()->toDateString());
});

it('clears the contact details when left blank', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create([
        'messenger_link' => 'https://m.me/acme',
        'last_interaction_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.workspaces.update-contact', $workspace), [
            'messenger_link' => '',
            'last_interaction_at' => '',
        ])
        ->assertSessionHasNoErrors();

    $workspace->refresh();

    expect($workspace->messenger_link)->toBeNull()
        ->and($workspace->last_interaction_at)->toBeNull();
});

it('rejects an invalid link and a future interaction date', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.workspaces.update-contact', $workspace), [
            'messenger_link' => 'not a url',
            'last_interaction_at' => Carbon::tomorrow()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors(['messenger_link', 'last_interaction_at']);
});

it('forbids non-admins from editing contact details', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->put(route('admin.workspaces.update-contact', $workspace), [
            'messenger_link' => 'https://m.me/acme',
        ]);

    expect($workspace->refresh()->messenger_link)->toBeNull();
});
