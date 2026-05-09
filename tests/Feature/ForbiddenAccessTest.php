<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users without access see the custom access denied screen', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $response = $this->actingAs($user)->get(route('workspaces.checklist.index', $workspace));

    $response->assertStatus(403);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('errors/403')
    );
});

test('unauthenticated users are redirected to login for restricted routes', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
