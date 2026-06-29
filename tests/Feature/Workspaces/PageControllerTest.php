<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('owner can view pages index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($workspace)->create();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/pages")
        ->assertOk();
});

test('non-member cannot view pages index', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/pages")
        ->assertForbidden();
});

test('owner can update a page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($owner)->create();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/{$page->id}/edit")
        ->put("/workspaces/{$workspace->slug}/pages/{$page->id}", [
            'name' => 'Renamed Page',
            'parcel_journey_enabled' => false,
            'owner_id' => $owner->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($page->fresh()->name)->toBe('Renamed Page');
});

test('owner can update today page budget from pages index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($owner)->create();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages")
        ->put("/workspaces/{$workspace->slug}/pages/{$page->id}/budget", [
            'budget' => '1234.50',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/pages");

    $record = PageDailyBudgetRecord::where('workspace_id', $workspace->id)
        ->where('page_id', $page->id)
        ->whereDate('date', now()->toDateString())
        ->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->budget)->toBe(1234.50);
});

test('cannot update a page from a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignPage = Page::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/pages/{$foreignPage->id}", [
            'name' => 'Hijacked',
            'parcel_journey_enabled' => false,
            'owner_id' => $owner->id,
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('archive deactivates the page; restore reactivates it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/pages/{$page->id}/archive")
        ->assertRedirect();

    expect($page->fresh()->status)->toBe('inactive');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/pages/{$page->id}/restore")
        ->assertRedirect();

    expect($page->fresh()->status)->toBe('active');
});

test('archive across workspaces returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignPage = Page::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspaceA->slug}/pages/{$foreignPage->id}/archive")
        ->assertForbidden();
});

test('validatePancakeToken returns valid:true when Pancake API confirms success', function () {
    Http::fake([
        'pages.fm/*' => Http::response(['success' => true, 'data' => []], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pancake-token", [
            'page_id' => 'page-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', true);
});

test('validatePancakeToken returns valid:false when Pancake API reports unsuccessful', function () {
    Http::fake([
        'pages.fm/api/public_api/v1/pages/*/page_customers' => Http::response(['success' => false, 'message' => 'bad token'], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pancake-token", [
            'page_id' => 'page-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false);
});

test('validateBotcakeToken returns valid:true on 2xx upstream response', function () {
    Http::fake([
        'botcake.io/api/public_api/v1/pages/*/flows*' => Http::response(['data' => []], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-botcake-token", [
            'page_id' => 'page-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', true);
});

test('validateBotcakeToken returns valid:false on upstream failure', function () {
    Http::fake([
        'botcake.io/api/public_api/v1/pages/*/flows*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-botcake-token", [
            'page_id' => 'page-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false);
});

test('guest is redirected to login from pages index', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->get("/workspaces/{$workspace->slug}/pages")
        ->assertRedirect('/login');
});

// ----- Filter & sort coverage -----

test('pages index filter[search] narrows by partial name', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Hat Catalog']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Other Page']);

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?filter[search]=Hat")
        ->assertOk();

    // Inertia response — pull props
    $names = collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();

    expect($names)->toBe(['Hat Catalog']);
});

test('pages index sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Charlie']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Alpha']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Bravo']);

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?sort=name")
        ->assertOk();

    $names = collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('pages index sort=-name returns descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Alpha']);
    Page::factory()->forWorkspace($w)->create(['name' => 'Bravo']);

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?sort=-name")
        ->assertOk();

    $names = collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();

    expect($names)->toBe(['Bravo', 'Alpha']);
});

test('pages index sort by orders_last_synced_at descending puts most-recent first', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($w)->create(['name' => 'Old', 'orders_last_synced_at' => now()->subDays(10)]);
    Page::factory()->forWorkspace($w)->create(['name' => 'New', 'orders_last_synced_at' => now()->subDay()]);
    Page::factory()->forWorkspace($w)->neverSynced()->create(['name' => 'Never']);

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?sort=-orders_last_synced_at")
        ->assertOk();

    $names = collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();

    // First two ordered by recency desc; null can be at end depending on DB
    expect(array_slice($names, 0, 2))->toBe(['New', 'Old']);
});

test('pages index sort by shop_name uses custom sort', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $shopZ = Shop::factory()->forWorkspace($w)->create(['name' => 'Zebra Shop']);
    $shopA = Shop::factory()->forWorkspace($w)->create(['name' => 'Apple Shop']);
    Page::factory()->forShop($shopZ)->create(['name' => 'P1']);
    Page::factory()->forShop($shopA)->create(['name' => 'P2']);

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?sort=shop_name")
        ->assertOk();

    $names = collect($response->getOriginalContent()->getData()['page']['props']['pages']['data'])
        ->pluck('name')->all();

    // Apple Shop's page first, then Zebra Shop's
    expect($names)->toBe(['P2', 'P1']);
});

test('pages index per_page limits results', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (range(1, 5) as $_) {
        Page::factory()->forWorkspace($w)->create();
    }

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/pages?per_page=2")
        ->assertOk();

    $data = $response->getOriginalContent()->getData()['page']['props']['pages'];
    expect($data['per_page'])->toBe(2);
    expect($data['data'])->toHaveCount(2);
});
