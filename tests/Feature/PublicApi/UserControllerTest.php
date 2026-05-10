<?php

use App\Models\User;

test('returns only users that belong to the authenticated workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $member = makeWorkspaceMember($workspace, 'member');
    $stranger = User::factory()->create();

    $response = $this->getJson('/api/v1/public/users', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($owner->id, $member->id)
        ->and($ids)->not->toContain($stranger->id);
});

test('respects per_page parameter and caps it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    foreach (range(1, 5) as $_) {
        $u = User::factory()->create();
        $workspace->users()->attach($u->id, ['role' => 'member']);
    }

    $response = $this->getJson('/api/v1/public/users?per_page=2', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($response->json('per_page'))->toBe(2);
});

test('search filter narrows results by name or email', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $needle = User::factory()->create(['name' => 'Findable Person', 'email' => 'find@example.test']);
    $other = User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.test']);
    $workspace->users()->attach($needle->id, ['role' => 'member']);
    $workspace->users()->attach($other->id, ['role' => 'member']);

    $response = $this->getJson('/api/v1/public/users?filter[search]=findable', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($needle->id)
        ->and($ids)->not->toContain($other->id);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/users')->assertStatus(401);
});

test('per_page is capped at 100', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $response = $this->getJson('/api/v1/public/users?per_page=500', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($response->json('per_page'))->toBe(100);
});

test('default sort is by name ascending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $charlie = User::factory()->create(['name' => 'Charlie']);
    $alpha = User::factory()->create(['name' => 'Alpha']);
    $bravo = User::factory()->create(['name' => 'Bravo']);
    $workspace->users()->attach([$charlie->id, $alpha->id, $bravo->id], ['role' => 'member']);

    $response = $this->getJson('/api/v1/public/users', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    // The owner is in there too, but our 3 should be in alphabetical order.
    $ours = array_values(array_intersect($names, ['Alpha', 'Bravo', 'Charlie']));
    expect($ours)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('sort=-name returns descending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $a = User::factory()->create(['name' => 'A-User']);
    $z = User::factory()->create(['name' => 'Z-User']);
    $workspace->users()->attach([$a->id, $z->id], ['role' => 'member']);

    $names = collect($this->getJson('/api/v1/public/users?sort=-name', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk()->json('data'))->pluck('name')->all();

    $ours = array_values(array_filter($names, fn ($n) => str_ends_with($n, '-User')));
    expect($ours)->toBe(['Z-User', 'A-User']);
});

test('sort=email returns ascending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $u1 = User::factory()->create(['email' => 'aaa-y@example.test']);
    $u2 = User::factory()->create(['email' => 'zzz-y@example.test']);
    $workspace->users()->attach([$u1->id, $u2->id], ['role' => 'member']);

    $emails = collect($this->getJson('/api/v1/public/users?sort=email', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk()->json('data'))->pluck('email')->all();

    $ours = array_values(array_filter($emails, fn ($e) => str_ends_with($e, '-y@example.test')));
    expect($ours)->toBe(['aaa-y@example.test', 'zzz-y@example.test']);
});

test('filter[has_pancake_account]=false does not exclude users without pancake accounts', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $without = User::factory()->create();
    $workspace->users()->attach($without->id, ['role' => 'member']);

    $ids = collect($this->getJson('/api/v1/public/users?filter[has_pancake_account]=false', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toContain($without->id);
});

test('include=pancakeAccounts loads relation in response', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $u = User::factory()->create();
    $workspace->users()->attach($u->id, ['role' => 'member']);

    $response = $this->getJson('/api/v1/public/users?include=pancakeAccounts', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $first = collect($response->json('data'))->firstWhere('id', $u->id);
    expect($first)->toHaveKey('pancake_accounts');
});

test('combining filter[search] + sort works as expected', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $alpha = User::factory()->create(['name' => 'Findable Alpha-Z']);
    $bravo = User::factory()->create(['name' => 'Findable Bravo-Z']);
    $other = User::factory()->create(['name' => 'Different']);
    $workspace->users()->attach([$alpha->id, $bravo->id, $other->id], ['role' => 'member']);

    $names = collect($this->getJson('/api/v1/public/users?filter[search]=Findable&sort=-name', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Findable Bravo-Z', 'Findable Alpha-Z']);
});
