<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Modules\Products\Models\Product;

test('pages list is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    Page::factory()->forWorkspace($a)->create(['name' => 'Mine']);
    Page::factory()->forWorkspace($b)->create(['name' => 'NotMine']);

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$a->slug}/pages")
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Mine')->and($names)->not->toContain('NotMine');
});

test('shops list is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    Shop::factory()->forWorkspace($a)->create(['name' => 'A']);
    Shop::factory()->forWorkspace($b)->create(['name' => 'B']);

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$a->slug}/shops")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['A']);
});

test('teams list is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    Team::factory()->create(['workspace_id' => $a->id, 'name' => 'Alpha']);
    Team::factory()->create(['workspace_id' => $b->id, 'name' => 'Beta']);

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$a->slug}/teams")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Alpha']);
});

test('products list is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    $a->update(['products_module_enabled' => true]);

    Product::factory()->create(['workspace_id' => $a->id, 'owner_id' => $owner->id, 'name' => 'A']);
    Product::factory()->create(['workspace_id' => $b->id, 'owner_id' => $owner->id, 'name' => 'B']);

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$a->slug}/products")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['A']);
});

test('users list is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($a);
    $stranger = User::factory()->create();

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$a->slug}/users")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($owner->id, $member->id)
        ->and($ids)->not->toContain($stranger->id);
});

test('search filter narrows pages by partial name', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($workspace)->create(['name' => 'Findable Hat Page']);
    Page::factory()->forWorkspace($workspace)->create(['name' => 'Other Thing']);

    $response = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/pages?filter[search]=Findable")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Findable Hat Page']);
});

test('guests get 401/redirect on browser-api endpoints', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson("/api/workspaces/{$workspace->slug}/pages")
        ->assertUnauthorized();
});

// ----- Filter & sort coverage -----

test('pages list ?sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Charlie Page']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Alpha Page']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Bravo Page']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/pages?sort=name")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Alpha Page', 'Bravo Page', 'Charlie Page']);
});

test('pages list ?sort=-name returns descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Charlie']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Alpha']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Bravo']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/pages?sort=-name")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

test('pages list ?sort=id returns by id ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $p1 = Page::factory()->forWorkspace($w)->create();
    $p2 = Page::factory()->forWorkspace($w)->create();

    $ids = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/pages?sort=id")
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$p1->id, $p2->id]);
});

test('pages list rejects unknown sort field', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/pages?sort=hax")
        ->assertStatus(400);
});

test('pages list filter[search] is case-insensitive partial match', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'BETA Hat Page']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Other Thing']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/pages?filter[search]=beta")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['BETA Hat Page']);
});

test('shops list sort name asc + filter[search] together', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Shop::factory()->forWorkspace($w)->create(['name' => 'Pancake Bravo']);
    Shop::factory()->forWorkspace($w)->create(['name' => 'Pancake Alpha']);
    Shop::factory()->forWorkspace($w)->create(['name' => 'Other Shop']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/shops?filter[search]=Pancake&sort=name")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Pancake Alpha', 'Pancake Bravo']);
});

test('teams list sort -name', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Alpha']);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Charlie']);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Bravo']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/teams?sort=-name")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

test('products list filter[search] returns matches', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $w->update(['products_module_enabled' => true]);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Findable Hat']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Different Thing']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/products?filter[search]=Findable")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Findable Hat']);
});

test('users list filter[search] matches name partially and is case-insensitive', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $bob = User::factory()->create(['name' => 'Bob the Builder']);
    $alice = User::factory()->create(['name' => 'Alice Wonderland']);
    $w->users()->attach([$bob->id, $alice->id], ['role' => 'member']);

    $names = collect($this->actingAs($owner)
        ->getJson("/api/workspaces/{$w->slug}/users?filter[search]=BUILDER")
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toContain('Bob the Builder')
        ->and($names)->not->toContain('Alice Wonderland');
});
