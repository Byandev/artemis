<?php

use App\Models\Page;

test('returns only pages from the authenticated workspace', function () {
    ['workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspaceA);

    Page::factory()->forWorkspace($workspaceA)->create(['name' => 'Mine A']);
    Page::factory()->forWorkspace($workspaceA)->create(['name' => 'Mine B']);
    Page::factory()->forWorkspace($workspaceB)->create(['name' => 'Other']);

    $response = $this->getJson('/api/v1/public/pages', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $names = collect($response->json('pages'))->pluck('name')->all();

    expect($names)->toHaveCount(2)
        ->and($names)->toContain('Mine A', 'Mine B')
        ->and($names)->not->toContain('Other');
});

test('returns empty array when workspace has no pages', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/pages', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertOk()
        ->assertExactJson(['pages' => []]);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/pages')->assertStatus(401);
});
