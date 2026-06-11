<?php

use App\Models\Shop;
use App\Models\User;

test('owner can view shops index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Shop::factory()->forWorkspace($workspace)->create();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/shops")
        ->assertOk();
});

test('non-member cannot view shops', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/shops")
        ->assertForbidden();
});

test('refresh on a foreign-workspace shop returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignShop = Shop::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspaceA->slug}/shops/{$foreignShop->id}/refresh")
        ->assertForbidden();
});

test('refresh-users on a foreign-workspace shop returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignShop = Shop::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspaceA->slug}/shops/{$foreignShop->id}/refresh-users")
        ->assertForbidden();
});

// ----- Filter & sort coverage -----

function shopsFromInertia($response): array
{
    return collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();
}

test('shops index filter[search] narrows by partial name', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Shop::factory()->forWorkspace($w)->create(['name' => 'Pancake Hat Shop']);
    Shop::factory()->forWorkspace($w)->create(['name' => 'Other Shop']);

    $names = shopsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/shops?filter[search]=Hat")->assertOk()
    );
    expect($names)->toBe(['Pancake Hat Shop']);
});

test('shops index sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Shop::factory()->forWorkspace($w)->create(['name' => 'Charlie']);
    Shop::factory()->forWorkspace($w)->create(['name' => 'Alpha']);
    Shop::factory()->forWorkspace($w)->create(['name' => 'Bravo']);

    $names = shopsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/shops?sort=name")->assertOk()
    );
    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('shops index per_page limits and paginates', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (range(1, 4) as $_) {
        Shop::factory()->forWorkspace($w)->create();
    }

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/shops?per_page=2")
        ->assertOk();

    $data = $response->getOriginalContent()->getData()['page']['props']['pages'];
    expect($data['per_page'])->toBe(2);
    expect($data['total'])->toBe(4);
    expect($data['data'])->toHaveCount(2);
});

test('shops index rejects unknown sort field', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/shops?sort=hax")
        ->assertStatus(400);
});
