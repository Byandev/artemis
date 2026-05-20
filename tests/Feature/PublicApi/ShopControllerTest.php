<?php

use App\Models\Shop;

test('returns only shops from the authenticated workspace', function () {
    ['workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspaceA);

    Shop::factory()->forWorkspace($workspaceA)->create(['name' => 'A1']);
    Shop::factory()->forWorkspace($workspaceB)->create(['name' => 'B1']);

    $response = $this->getJson('/api/v1/public/shops', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $names = collect($response->json('shops'))->pluck('name')->all();

    expect($names)->toBe(['A1']);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/shops')->assertStatus(401);
});
