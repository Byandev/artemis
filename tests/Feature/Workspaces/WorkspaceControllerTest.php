<?php

use App\Models\User;
use App\Models\Workspace;

test('index lists only workspaces the user belongs to', function () {
    ['user' => $owner, 'workspace' => $mine] = makeWorkspaceWithOwner();
    Workspace::factory()->create(); // someone else's

    $this->actingAs($owner)
        ->get('/workspaces')
        ->assertOk();

    expect($owner->fresh()->workspaces()->count())->toBe(1);
});

test('guest is redirected to login from workspaces index', function () {
    $this->get('/workspaces')->assertRedirect('/login');
});

test('authenticated user can store a workspace and is attached as owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/workspaces', [
            'name' => 'My New Space',
            'description' => 'Hello',
        ])
        ->assertRedirect();

    $workspace = Workspace::where('name', 'My New Space')->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->owner_id)->toBe($user->id);
    expect($workspace->hasMember($user))->toBeTrue();
});

test('store requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/workspaces/create')
        ->post('/workspaces', [])
        ->assertSessionHasErrors('name');
});

test('show redirects to dashboard for members', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}")
        ->assertRedirect("/workspaces/{$workspace->slug}/dashboard");
});

test('show is forbidden for non-members', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}")
        ->assertForbidden();
});

test('owner can update workspace name and description', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}", [
            'name' => 'Renamed',
            'description' => 'New desc',
        ])
        ->assertRedirect();

    $workspace->refresh();
    expect($workspace->name)->toBe('Renamed');
    expect($workspace->description)->toBe('New desc');
});

test('only the owner can delete a workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);

    $this->actingAs($member)
        ->delete("/workspaces/{$workspace->slug}")
        ->assertForbidden();

    expect(Workspace::find($workspace->id))->not->toBeNull();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}")
        ->assertRedirect('/workspaces');

    expect(Workspace::find($workspace->id))->toBeNull();
});

test('switch sets session current_workspace_id and redirects to dashboard', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/switch")
        ->assertRedirect("/workspaces/{$workspace->slug}/dashboard")
        ->assertSessionHas('current_workspace_id', $workspace->id);
});

test('switch is forbidden for non-members', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post("/workspaces/{$workspace->slug}/switch")
        ->assertForbidden();
});
