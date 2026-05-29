<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users without a workspace are sent to workspace setup', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('dashboard'))
        ->assertRedirect(route('workspaces.setup'));
});

test('authenticated users with a workspace are sent to that workspace dashboard', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('workspace.dashboard', $workspace->slug));
});
