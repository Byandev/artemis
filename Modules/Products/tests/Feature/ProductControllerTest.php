<?php

use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Models\Product;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

test('owner can view products index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/list")
        ->assertOk();
});

test('non-member cannot view products', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/products/list")
        ->assertForbidden();
});

test('owner can create a product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/products/create")
        ->post("/workspaces/{$workspace->slug}/products", [
            'name' => 'Widget',
            'code' => 'WID-1',
            'category' => 'Gadgets',
            'status' => 'Scaling',
            'description' => 'A nice widget',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/list");

    expect(Product::where('workspace_id', $workspace->id)
        ->where('name', 'Widget')->exists())->toBeTrue();
});

test('store validates required fields and status enum', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/products/create")
        ->post("/workspaces/{$workspace->slug}/products", [
            'status' => 'NotARealStatus',
        ])
        ->assertSessionHasErrors(['name', 'code', 'category', 'status']);
});

test('product code must be unique within workspace but can repeat across workspaces', function () {
    ['user' => $ownerA, 'workspace' => $workspaceA] = makeProductsWorkspace();
    ['user' => $ownerB, 'workspace' => $workspaceB] = makeProductsWorkspace();

    Product::factory()->create(['workspace_id' => $workspaceA->id, 'owner_id' => $ownerA->id, 'name' => 'X', 'code' => 'DUP', 'category' => 'C', 'status' => 'Scaling']);

    // Same code in same workspace -> error
    $this->actingAs($ownerA)
        ->from("/workspaces/{$workspaceA->slug}/products/create")
        ->post("/workspaces/{$workspaceA->slug}/products", [
            'name' => 'Y', 'code' => 'DUP', 'category' => 'C', 'status' => 'Scaling',
        ])
        ->assertSessionHasErrors('code');

    // Same code in another workspace -> ok
    $this->actingAs($ownerB)
        ->from("/workspaces/{$workspaceB->slug}/products/create")
        ->post("/workspaces/{$workspaceB->slug}/products", [
            'name' => 'Y', 'code' => 'DUP', 'category' => 'C', 'status' => 'Scaling',
        ])
        ->assertRedirect();
});

test('owner can update a product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'name' => 'Old', 'code' => 'C1', 'category' => 'X', 'status' => 'Scaling']);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/{$product->id}", [
            'name' => 'New',
            'code' => 'C1',
            'category' => 'X',
            'status' => 'Inactive',
        ])
        ->assertRedirect();

    expect($product->fresh()->name)->toBe('New');
    expect($product->fresh()->status)->toBe('Inactive');
});

test('cannot update product from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeProductsWorkspace();
    ['user' => $ownerB, 'workspace' => $workspaceB] = makeProductsWorkspace();
    $foreign = Product::factory()->create(['workspace_id' => $workspaceB->id, 'owner_id' => $ownerB->id, 'name' => 'F', 'code' => 'F1', 'category' => 'C', 'status' => 'Scaling']);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/products/{$foreign->id}", [
            'name' => 'Hijack', 'code' => 'F1', 'category' => 'C', 'status' => 'Scaling',
        ])
        ->assertForbidden();
});

test('owner can delete a product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'name' => 'X', 'code' => 'C', 'category' => 'C', 'status' => 'Scaling']);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/{$product->id}")
        ->assertRedirect();

    expect(Product::find($product->id))->toBeNull();
});

test('store rejects code longer than 10 characters', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/products/create")
        ->post("/workspaces/{$workspace->slug}/products", [
            'name' => 'X', 'code' => str_repeat('A', 11), 'category' => 'C', 'status' => 'Scaling',
        ])
        ->assertSessionHasErrors('code');
});

test('store rejects when shop_ids contains an unknown shop id', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/products/create")
        ->post("/workspaces/{$workspace->slug}/products", [
            'name' => 'P', 'code' => 'P1', 'category' => 'C', 'status' => 'Scaling',
            'shop_ids' => [999999],
        ])
        ->assertSessionHasErrors('shop_ids.0');
});

test('non-member cannot delete a product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->delete("/workspaces/{$workspace->slug}/products/{$product->id}")
        ->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
});

// ----- Filter & sort coverage -----

function productsFromInertia($response): array
{
    return collect($response->getOriginalContent()->getData()['page']['props']['products']['data'])
        ->pluck('name')->all();
}

test('products index filter[search] matches name and code', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Findable Hat', 'code' => 'AAA']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Other', 'code' => 'BBB-FIND']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Different', 'code' => 'CCC']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[search]=find")->assertOk()
    );

    expect($names)->toContain('Findable Hat')
        ->and($names)->toContain('Other')
        ->and($names)->not->toContain('Different');
});

test('products index filter[category] narrows exact match', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'A', 'category' => 'Health']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'B', 'category' => 'Beauty']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[category]=Health")->assertOk()
    );
    expect($names)->toBe(['A']);
});

test('products index filter[status] narrows exact match', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'A', 'status' => 'Scaling']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'B', 'status' => 'Failed']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[status]=Failed")->assertOk()
    );
    expect($names)->toBe(['B']);
});

test('products index defaults to created_at descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    $a = Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'First']);
    sleep(1);
    $b = Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Second']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list")->assertOk()
    );

    expect($names[0])->toBe('Second');
});

test('products index sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Bravo']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Alpha']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Charlie']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?sort=name")->assertOk()
    );
    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('products index sort=-name returns descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Alpha']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Bravo']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?sort=-name")->assertOk()
    );
    expect($names)->toBe(['Bravo', 'Alpha']);
});

test('products index combines filter[category] and sort=code', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Z', 'category' => 'Health', 'code' => 'B-1']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Y', 'category' => 'Health', 'code' => 'A-1']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'X', 'category' => 'Other', 'code' => 'A-0']);

    $names = productsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[category]=Health&sort=code")->assertOk()
    );
    expect($names)->toBe(['Y', 'Z']);
});

test('store attaches selected shops from same workspace and ignores foreign shops', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    $myShop = Shop::factory()->forWorkspace($workspace)->create();
    $foreignShop = Shop::factory()->forWorkspace($other)->create();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/products/create")
        ->post("/workspaces/{$workspace->slug}/products", [
            'name' => 'P', 'code' => 'P1', 'category' => 'C', 'status' => 'Scaling',
            'shop_ids' => [$myShop->id, $foreignShop->id],
        ])
        ->assertRedirect();

    $product = Product::where('code', 'P1')->first();
    expect($myShop->fresh()->product_id)->toBe($product->id);
    // Foreign shop should NOT have been linked
    expect($foreignShop->fresh()->product_id)->not->toBe($product->id);
});

function productPropsFromInertia($response): array
{
    return $response->getOriginalContent()->getData()['page']['props'];
}

test('products index returns summary counts per lifecycle stage', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->count(2)->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'status' => 'Scaling']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'status' => 'Testing']);
    Product::factory()->count(3)->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'status' => 'Inactive']);

    $props = productPropsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list")->assertOk()
    );

    expect($props['summary'])->toMatchArray([
        'total_product_count' => 6,
        'scaling_product_count' => 2,
        'testing_product_count' => 1,
        'inactive_product_count' => 3,
    ]);

    // Every stage is present so the tabs can render a count even at zero.
    expect(collect($props['statusCounts'])->all())
        ->toHaveKeys(Product::STATUSES)
        ->and(collect($props['statusCounts'])['Failed'])->toBe(0);
});

test('products index summary ignores the status filter but honours search', function () {
    ['user' => $owner, 'workspace' => $w] = makeProductsWorkspace();
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Findable Hat', 'status' => 'Scaling']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Findable Cap', 'status' => 'Testing']);
    Product::factory()->create(['workspace_id' => $w->id, 'owner_id' => $owner->id, 'name' => 'Unrelated', 'status' => 'Scaling']);

    // Selecting a tab must not collapse the other tabs' counts to zero.
    $props = productPropsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[status]=Scaling")->assertOk()
    );
    expect($props['summary']['total_product_count'])->toBe(3)
        ->and(collect($props['statusCounts'])['Testing'])->toBe(1);

    // Search does narrow them, so the cards describe the listed slice.
    $props = productPropsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/products/list?filter[search]=findable")->assertOk()
    );
    expect($props['summary']['total_product_count'])->toBe(2)
        ->and($props['summary']['scaling_product_count'])->toBe(1);
});

test('product pages 404 when the workspace has the module switched off', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id]);

    $this->actingAs($owner)->get("/workspaces/{$workspace->slug}/products/list")->assertNotFound();
    $this->actingAs($owner)->get("/workspaces/{$workspace->slug}/products/analytics")->assertNotFound();
    $this->actingAs($owner)->get("/workspaces/{$workspace->slug}/products/create")->assertNotFound();
    $this->actingAs($owner)->get("/workspaces/{$workspace->slug}/products/{$product->id}/edit")->assertNotFound();
    $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->slug}/products")->assertNotFound();

    // ...and are reachable again the moment the admin turns it on.
    $workspace->update(['products_module_enabled' => true]);
    $this->actingAs($owner)->get("/workspaces/{$workspace->slug}/products/list")->assertOk();
});
