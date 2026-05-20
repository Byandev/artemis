<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Register a tiny test-only route protected by auth + workspace middleware that
// returns the resolved workspace id. This isolates the middleware from
// controller-specific logic so behaviour is unambiguous.
beforeEach(function () {
    Route::middleware(['web', 'auth', 'workspace'])
        ->get('/__test/workspace-check', function (Request $request) {
            return response()->json([
                'workspace_id' => $request->get('workspace')->id,
            ]);
        });
});

test('returns 403 JSON when no workspace can be resolved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/__test/workspace-check')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'You do not have permission to view this page.',
        ]);
});

test('returns 403 when user is not a member of the requested workspace', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson('/__test/workspace-check', [
            'X-Workspace-Id' => $workspace->id,
        ])
        ->assertForbidden();
});

test('returns 403 when X-Workspace-Id points at a missing workspace', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/__test/workspace-check', [
            'X-Workspace-Id' => 999999,
        ])
        ->assertForbidden();
});

test('owner can pass workspace middleware via X-Workspace-Id header', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson('/__test/workspace-check', [
            'X-Workspace-Id' => $workspace->id,
        ])
        ->assertOk()
        ->assertJson(['workspace_id' => $workspace->id]);
});

test('non-owner member can pass workspace middleware', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->getJson('/__test/workspace-check', [
            'X-Workspace-Id' => $workspace->id,
        ])
        ->assertOk();
});

test('falls back to session current_workspace_id when header is absent', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->getJson('/__test/workspace-check')
        ->assertOk()
        ->assertJson(['workspace_id' => $workspace->id]);
});

test('returns plain 403 (not JSON) for non-JSON requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/__test/workspace-check')
        ->assertForbidden();
});

test('unauthenticated requests are bounced by the auth middleware before workspace check', function () {
    $this->get('/__test/workspace-check')
        ->assertRedirect('/login');
});
