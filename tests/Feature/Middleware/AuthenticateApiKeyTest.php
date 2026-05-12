<?php

use App\Models\Workspace;
use App\Models\WorkspaceApiKey;

test('rejects request without API key header', function () {
    $this->getJson('/api/v1/public/health')
        ->assertStatus(401)
        ->assertJson(['error' => 'Missing API key.']);
});

test('rejects request with invalid bearer token', function () {
    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer not-a-real-key',
    ])
        ->assertStatus(401)
        ->assertJson(['error' => 'Invalid API key.']);
});

test('rejects request with invalid X-API-Key header', function () {
    $this->getJson('/api/v1/public/health', [
        'X-API-Key' => 'not-a-real-key',
    ])
        ->assertStatus(401)
        ->assertJson(['error' => 'Invalid API key.']);
});

test('accepts a valid Bearer token', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'workspace' => $workspace->name,
        ]);
});

test('accepts a valid X-API-Key header', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/health', [
        'X-API-Key' => $raw,
    ])->assertOk();
});

test('Bearer header takes precedence over X-API-Key', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $valid] = makeApiKey($workspace);

    // Bearer is valid, X-API-Key is bogus — must succeed.
    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$valid,
        'X-API-Key' => 'garbage',
    ])->assertOk();
});

test('stamps last_used_at on successful authentication', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['model' => $apiKey, 'raw' => $raw] = makeApiKey($workspace);

    expect($apiKey->last_used_at)->toBeNull();

    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($apiKey->fresh()->last_used_at)->not->toBeNull();
});

test('keys are isolated per workspace', function () {
    $a = makeWorkspaceWithOwner();
    $b = makeWorkspaceWithOwner();

    ['raw' => $rawA] = makeApiKey($a['workspace']);

    // Key A authenticates as workspace A, not B.
    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$rawA,
    ])
        ->assertOk()
        ->assertJsonPath('workspace', $a['workspace']->name);
});

test('findByRawKey returns null for unknown raw key', function () {
    expect(WorkspaceApiKey::findByRawKey('nonsense'))->toBeNull();
});

test('reveal returns the original raw key', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['model' => $apiKey, 'raw' => $raw] = makeApiKey($workspace);

    expect($apiKey->reveal())->toBe($raw);
});

test('rejects empty Bearer token (Authorization: Bearer )', function () {
    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer ',
    ])->assertStatus(401)->assertJson(['error' => 'Missing API key.']);
});

test('rejects malformed Authorization header (no Bearer prefix)', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/health', [
        'Authorization' => $raw, // missing 'Bearer ' prefix → falls through to X-API-Key (also empty)
    ])->assertStatus(401);
});

test('does not authenticate after the key has been deleted', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['model' => $apiKey, 'raw' => $raw] = makeApiKey($workspace);

    $apiKey->delete();

    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertStatus(401)->assertJson(['error' => 'Invalid API key.']);
});

test('two distinct API keys both authenticate independently for the same workspace', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $r1] = makeApiKey($workspace, 'k1');
    ['raw' => $r2] = makeApiKey($workspace, 'k2');

    $this->getJson('/api/v1/public/health', ['Authorization' => 'Bearer '.$r1])->assertOk();
    $this->getJson('/api/v1/public/health', ['Authorization' => 'Bearer '.$r2])->assertOk();
});
