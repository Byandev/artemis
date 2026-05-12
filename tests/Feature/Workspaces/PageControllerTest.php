<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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
            'shop_id' => $page->shop_id,
            'name' => 'Renamed Page',
            'parcel_journey_enabled' => false,
            'owner_id' => $owner->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($page->fresh()->name)->toBe('Renamed Page');
});

test('cannot update a page from a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignPage = Page::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/pages/{$foreignPage->id}", [
            'shop_id' => $foreignPage->shop_id,
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

test('refresh resets sync timestamp and dispatches a job', function () {
    Bus::fake();

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->recentlySynced()->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/pages/{$page->id}/refresh")
        ->assertRedirect();

    expect($page->fresh()->orders_last_synced_at)->toBeNull();
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchPageOrders::class);
});

test('validatePosToken returns valid:true on successful upstream response', function () {
    Http::fake([
        'pos.pages.fm/*' => Http::response(['shop' => ['name' => 'Test']], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', true);
});

test('validatePosToken returns valid:false on upstream failure', function () {
    Http::fake([
        'pos.pages.fm/*' => Http::response([], 401),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false);
});

test('validatePosToken requires membership', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertForbidden();
});

test('validatePosToken validates input fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pos-token", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shop_id', 'token']);
});

test('validatePosToken returns valid:false on connection exception', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/pages/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'Could not reach Pancake API.');
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

test('store creates a page and shop after Pancake API confirms', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => [
                'id' => 123,
                'name' => 'My Shop',
                'avatar_url' => 'https://cdn.example.test/avatar.png',
                'pages' => [
                    ['id' => 9001, 'name' => 'Hat Page'],
                ],
            ],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [
            'id' => 9001,
            'shop_id' => 123,
            'name' => 'Hat Page',
            'pos_token' => 'valid-pos-token',
            'parcel_journey_enabled' => false,
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/pages");

    expect(Page::where('id', 9001)->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    expect(\App\Models\Shop::where('id', 123)->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchPageOrders::class);
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchShopCustomers::class);
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchShopUsers::class);
});

test('store rejects invalid POS token (Pancake returns failed response)', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response(['error' => 'invalid'], 401),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [
            'id' => 9001,
            'shop_id' => 123,
            'name' => 'Hat Page',
            'pos_token' => 'bogus',
            'parcel_journey_enabled' => false,
        ])
        ->assertSessionHasErrors('pos_token');

    expect(Page::where('id', 9001)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('store rejects when Pancake response does not contain the requested page id', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => [
                'name' => 'My Shop',
                'pages' => [
                    ['id' => 1, 'name' => 'Other Page'],
                ],
            ],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [
            'id' => 9001,
            'shop_id' => 123,
            'name' => 'Hat Page',
            'pos_token' => 'valid',
            'parcel_journey_enabled' => false,
        ])
        ->assertSessionHasErrors('id');

    expect(Page::where('id', 9001)->exists())->toBeFalse();
});

test('store validates required fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [])
        ->assertSessionHasErrors(['id', 'shop_id', 'name', 'pos_token']);
});

test('store rejects duplicate page id (already in any workspace)', function () {
    Bus::fake();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($other)->create(['id' => 9001]);

    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['name' => 'Shop', 'pages' => [['id' => 9001, 'name' => 'P']]],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [
            'id' => 9001,
            'shop_id' => 123,
            'name' => 'Hat Page',
            'pos_token' => 'valid',
            'parcel_journey_enabled' => false,
        ])
        ->assertSessionHasErrors('id');
});

test('non-member cannot reach store', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->from("/workspaces/{$workspace->slug}/pages/create")
        ->post("/workspaces/{$workspace->slug}/pages", [
            'id' => 9001,
            'shop_id' => 123,
            'name' => 'X',
            'pos_token' => 'tok',
            'parcel_journey_enabled' => false,
        ])
        ->assertForbidden();
});

test('refresh on a foreign-workspace page returns 403', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    $foreign = Page::factory()->forWorkspace($b)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$a->slug}/pages/{$foreign->id}/refresh")
        ->assertForbidden();
});

test('non-member cannot view pages create page', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/pages/create")
        ->assertForbidden();
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
    $shopZ = \App\Models\Shop::factory()->forWorkspace($w)->create(['name' => 'Zebra Shop']);
    $shopA = \App\Models\Shop::factory()->forWorkspace($w)->create(['name' => 'Apple Shop']);
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
