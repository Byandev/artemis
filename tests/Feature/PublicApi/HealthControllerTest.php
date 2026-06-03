<?php

test('returns ok and resolved workspace name', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/health', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'workspace' => $workspace->name,
        ]);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/health')->assertStatus(401);
});
