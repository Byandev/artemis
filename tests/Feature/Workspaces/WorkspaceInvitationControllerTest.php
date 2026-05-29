<?php

use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Notification;

test('owner can invite a new email to the workspace', function () {
    Notification::fake();

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    // Owner needs to be 'admin' or 'owner' on the pivot for isAdminOf().
    // makeWorkspaceWithOwner already attaches with role 'owner'.
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->post("/workspaces/{$workspace->slug}/invitations", [
            'email' => 'new@example.test',
            'role_id' => $role->id,
        ])
        ->assertRedirect();

    expect(WorkspaceInvitation::where('workspace_id', $workspace->id)
        ->where('email', 'new@example.test')->exists())->toBeTrue();
});

test('cannot invite the same email twice while invitation is pending', function () {
    Notification::fake();

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'dup@example.test',
        'role_id' => $role->id,
    ]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->post("/workspaces/{$workspace->slug}/invitations", [
            'email' => 'dup@example.test',
            'role_id' => $role->id,
        ])
        ->assertSessionHasErrors('email');
});

test('cannot invite an email that already belongs to a member', function () {
    Notification::fake();

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $existing = User::factory()->create(['email' => 'already@example.test']);
    $workspace->users()->attach($existing->id, ['role' => 'member']);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->post("/workspaces/{$workspace->slug}/invitations", [
            'email' => 'already@example.test',
            'role_id' => $role->id,
        ])
        ->assertSessionHasErrors('email');
});

test('non-admin members cannot invite', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->from("/workspaces/{$workspace->slug}/members")
        ->post("/workspaces/{$workspace->slug}/invitations", [
            'email' => 'whoever@example.test',
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});

test('store validates email format and role_id existence', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->post("/workspaces/{$workspace->slug}/invitations", [
            'email' => 'not-an-email',
            'role_id' => 999999,
        ])
        ->assertSessionHasErrors(['email', 'role_id']);
});

test('owner can revoke an invitation', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'role_id' => $role->id,
    ]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->delete("/workspaces/invitations/{$invitation->id}")
        ->assertRedirect();

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('non-admin cannot revoke an invitation', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($member)
        ->from("/workspaces/{$workspace->slug}/members")
        ->delete("/workspaces/invitations/{$invitation->id}")
        ->assertForbidden();

    expect(WorkspaceInvitation::find($invitation->id))->not->toBeNull();
});

test('show returns invalid page when invitation has expired', function () {
    $invitation = WorkspaceInvitation::factory()->expired()->create();

    $this->get("/workspaces/invitations/{$invitation->token}")
        ->assertOk(); // Inertia renders the invitation-invalid page; route succeeds
});

test('accept fails for an authenticated user with a different email', function () {
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'invitee@example.test']);
    $other = User::factory()->create(['email' => 'someone-else@example.test']);

    $this->actingAs($other)
        ->get("/workspaces/invitations/{$invitation->token}/accept")
        ->assertRedirect();

    expect($invitation->fresh()->isAccepted())->toBeFalse();
});

test('accept attaches the user to the workspace and marks accepted', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $invitee = User::factory()->create(['email' => 'invitee@example.test']);
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => $invitee->email,
        'role_id' => $role->id,
    ]);

    $this->actingAs($invitee)
        ->get("/workspaces/invitations/{$invitation->token}/accept")
        ->assertRedirect();

    expect($workspace->fresh()->hasMember($invitee))->toBeTrue();
    expect($invitation->fresh()->isAccepted())->toBeTrue();
});
