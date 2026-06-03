<?php

use App\Models\User;
use App\Models\WorkspaceApiKey;

test('owner can view the API keys page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/api-keys")
        ->assertOk();
});

test('non-member cannot view API keys', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/api-keys")
        ->assertForbidden();
});

test('owner can create an API key and the raw key is flashed once', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $response = $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/api-keys")
        ->post("/workspaces/{$workspace->slug}/api-keys", ['name' => 'My Key']);

    $response->assertRedirect()->assertSessionHas('newApiKey');

    $raw = session('newApiKey');
    expect($raw)->toBeString()->toStartWith('art_');

    $key = WorkspaceApiKey::where('workspace_id', $workspace->id)->first();
    expect($key)->not->toBeNull();
    expect($key->name)->toBe('My Key');
    expect($key->key)->toBe(hash('sha256', $raw));
});

test('store validates that name is required', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/api-keys")
        ->post("/workspaces/{$workspace->slug}/api-keys", [])
        ->assertSessionHasErrors('name');
});

test('owner can reveal an API key value', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['model' => $key, 'raw' => $raw] = makeApiKey($workspace);

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/api-keys/{$key->id}/reveal")
        ->assertOk()
        ->assertJson(['key' => $raw]);
});

test('reveal returns 404 if the key belongs to a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    ['model' => $foreignKey] = makeApiKey($workspaceB);

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspaceA->slug}/api-keys/{$foreignKey->id}/reveal")
        ->assertNotFound();
});

test('owner can revoke (delete) an API key', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['model' => $key] = makeApiKey($workspace);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/api-keys")
        ->delete("/workspaces/{$workspace->slug}/api-keys/{$key->id}")
        ->assertRedirect();

    expect(WorkspaceApiKey::find($key->id))->toBeNull();
});

test('destroy returns 404 if key belongs to a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    ['model' => $foreignKey] = makeApiKey($workspaceB);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspaceA->slug}/api-keys/{$foreignKey->id}")
        ->assertNotFound();

    expect(WorkspaceApiKey::find($foreignKey->id))->not->toBeNull();
});

test('store rejects names longer than 255 characters', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/api-keys")
        ->post("/workspaces/{$workspace->slug}/api-keys", ['name' => str_repeat('x', 256)])
        ->assertSessionHasErrors('name');
});

test('non-owner non-admin member cannot create API keys', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->from("/workspaces/{$workspace->slug}/api-keys")
        ->post("/workspaces/{$workspace->slug}/api-keys", ['name' => 'attempt'])
        ->assertForbidden();
});

test('reveal returns 422 for keys lacking encrypted token (legacy keys)', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $key = WorkspaceApiKey::create([
        'workspace_id' => $workspace->id,
        'name' => 'legacy',
        'key' => hash('sha256', 'legacy-raw'),
        'key_prefix' => 'legacy',
        'key_encrypted' => null,
    ]);

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/api-keys/{$key->id}/reveal")
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

test('guests are redirected from API key endpoints', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->get("/workspaces/{$workspace->slug}/api-keys")
        ->assertRedirect('/login');
});
