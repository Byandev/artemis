<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Jobs\FetchShopOrders;
use Modules\Pancake\Jobs\FetchShopUsers;

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
        ->post("/workspaces/{$workspaceA->slug}/shops/{$foreignShop->id}/refresh-orders")
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

test('refresh-pages re-syncs the shop pages from the POS API', function () {
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => [
                'name' => 'S',
                'pages' => [
                    ['id' => 7001, 'name' => 'Renamed Page'],
                    ['id' => 7002, 'name' => 'Newly Added'],
                ],
            ],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create(['pos_token' => 'tok']);
    Page::factory()->create([
        'id' => 7001,
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
        'name' => 'Old Name',
    ]);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/shops/{$shop->id}/refresh-pages")
        ->assertRedirect("/workspaces/{$workspace->slug}/shops");

    // New page discovered, existing page's name refreshed.
    expect(Page::where('id', 7002)->where('shop_id', $shop->id)->exists())->toBeTrue();
    expect(Page::find(7001)->name)->toBe('Renamed Page');
});

test('refresh-pages on a foreign-workspace shop returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignShop = Shop::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspaceA->slug}/shops/{$foreignShop->id}/refresh-pages")
        ->assertForbidden();
});

// ----- Destroy (delete shop + related data) -----

test('owner can delete a shop and its related data is removed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    // Cascading child (FK): a page belonging to the shop.
    Page::factory()->create([
        'id' => 5001,
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
    ]);

    // Non-cascading children keyed by shop_id — must be cleaned up explicitly.
    DB::table('pancake_orders')->insert([
        'order_number' => 'ON-1',
        'status' => 1,
        'status_name' => 'new',
        'shop_id' => $shop->id,
        'page_id' => 5001,
        'workspace_id' => $workspace->id,
        'customer_id' => '11111111-1111-1111-1111-111111111111',
        'inserted_at' => now(),
    ]);
    DB::table('pancake_customers')->insert([
        'id' => '22222222-2222-2222-2222-222222222222',
        'shop_id' => $shop->id,
        'customer_id' => '33333333-3333-3333-3333-333333333333',
        'name' => 'Cust',
        'fb_id' => 'fb-1',
    ]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->delete("/workspaces/{$workspace->slug}/shops/{$shop->id}")
        ->assertRedirect("/workspaces/{$workspace->slug}/shops");

    expect(Shop::where('id', $shop->id)->exists())->toBeFalse();
    expect(Page::where('id', 5001)->exists())->toBeFalse();
    expect(DB::table('pancake_orders')->where('shop_id', $shop->id)->exists())->toBeFalse();
    expect(DB::table('pancake_customers')->where('shop_id', $shop->id)->exists())->toBeFalse();
});

test('deleting a foreign-workspace shop returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignShop = Shop::factory()->forWorkspace($workspaceB)->create();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspaceA->slug}/shops/{$foreignShop->id}")
        ->assertForbidden();

    expect(Shop::where('id', $foreignShop->id)->exists())->toBeTrue();
});

test('non-member cannot delete a shop', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->delete("/workspaces/{$workspace->slug}/shops/{$shop->id}")
        ->assertForbidden();

    expect(Shop::where('id', $shop->id)->exists())->toBeTrue();
});

// ----- Validate POS token -----

test('validatePosToken returns valid:true on successful upstream response', function () {
    Http::fake([
        'pos.pages.fm/*' => Http::response(['shop' => ['name' => 'Test']], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/shops/validate-pos-token", [
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
        ->postJson("/workspaces/{$workspace->slug}/shops/validate-pos-token", [
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
        ->postJson("/workspaces/{$workspace->slug}/shops/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertForbidden();
});

test('validatePosToken validates input fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/shops/validate-pos-token", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shop_id', 'token']);
});

test('validatePosToken returns valid:false on connection exception', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/shops/validate-pos-token", [
            'shop_id' => 'shop-1',
            'token' => 'abc',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'Could not reach Pancake API.');
});

// ----- Store (add shop, auto-fetch pages) -----

test('store creates a shop, stores the POS token, and auto-fetches its pages', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => [
                'id' => 123,
                'name' => 'My Shop',
                'avatar_url' => 'https://cdn.example.test/avatar.png',
                'pages' => [
                    ['id' => 9001, 'name' => 'Hat Page'],
                    ['id' => 9002, 'name' => 'Cap Page'],
                ],
            ],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'valid-pos-token',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/shops");

    expect(Shop::where('id', 123)->where('workspace_id', $workspace->id)
        ->where('pos_token', 'valid-pos-token')->exists())->toBeTrue();
    expect(Page::where('id', 9001)->where('shop_id', 123)->exists())->toBeTrue();
    expect(Page::where('id', 9002)->where('shop_id', 123)->exists())->toBeTrue();
    Bus::assertDispatched(FetchShopOrders::class);
    Bus::assertDispatched(FetchShopUsers::class);
});

test('store auto-attaches the new shop to the connecting user single team', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['id' => 123, 'name' => 'My Shop', 'pages' => []],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $owner->teams()->attach($team);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'valid-pos-token',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/shops");

    expect(Shop::find(123)->teams()->pluck('teams.id')->all())->toBe([$team->id]);
});

test('store auto-attaches the new shop to ALL of the connecting user teams in the workspace', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['id' => 123, 'name' => 'My Shop', 'pages' => []],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);
    $owner->teams()->attach([$teamA->id, $teamB->id]);

    // A team the user is NOT on, and a team in another workspace: neither should attach.
    Team::factory()->create(['workspace_id' => $workspace->id]);
    $otherWorkspaceTeam = Team::factory()->create();
    $owner->teams()->attach($otherWorkspaceTeam);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'valid-pos-token',
        ])
        ->assertRedirect();

    expect(Shop::find(123)->teams()->pluck('teams.id')->sort()->values()->all())
        ->toBe(collect([$teamA->id, $teamB->id])->sort()->values()->all());
});

test('store creates the shop with no team when the connecting user is on no team', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['id' => 123, 'name' => 'My Shop', 'pages' => []],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'valid-pos-token',
        ])
        ->assertRedirect();

    expect(Shop::find(123))->not->toBeNull()
        ->and(Shop::find(123)->teams()->count())->toBe(0);
});

test('store rejects an invalid POS token', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response(['error' => 'invalid'], 401),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'bogus',
        ])
        ->assertSessionHasErrors('pos_token');

    expect(Shop::where('id', 123)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('store validates required fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->post("/workspaces/{$workspace->slug}/shops", [])
        ->assertSessionHasErrors(['shop_id', 'pos_token']);
});

test('store rejects a shop already added to the workspace', function () {
    Bus::fake();
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['name' => 'Dup', 'pages' => [['id' => 1, 'name' => 'P']]],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Shop::factory()->forWorkspace($workspace)->create(['id' => 123]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/shops")
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'valid',
        ])
        ->assertSessionHasErrors('shop_id');
});

test('non-member cannot reach shop store', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post("/workspaces/{$workspace->slug}/shops", [
            'shop_id' => 123,
            'pos_token' => 'tok',
        ])
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
