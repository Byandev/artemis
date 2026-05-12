<?php

use App\Models\User;

// /api/v1/workspace/* uses ['auth', 'workspace'] middleware. Membership is
// resolved via X-Workspace-Id header. We exercise membership behaviour here.

test('analytics endpoint requires authentication', function () {
    $this->getJson('/api/v1/workspace/analytics')->assertUnauthorized();
});

test('analytics endpoint forbids non-member', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson('/api/v1/workspace/analytics', [
            'X-Workspace-Id' => $workspace->id,
        ])
        ->assertForbidden();
});

test('analytics endpoint returns 403 if X-Workspace-Id is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/workspace/analytics')
        ->assertForbidden();
});

test('analytics endpoint returns 403 if X-Workspace-Id points to a missing workspace', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/workspace/analytics', [
            'X-Workspace-Id' => 9999999,
        ])
        ->assertForbidden();
});
