<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/** An active item linked to a product on the given lifecycle status. */
function itemOnProductStatus($workspace, $owner, string $sku, string $status): InventoryItem
{
    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'owner_id' => $owner->id,
        'status' => $status,
    ]);

    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'is_active' => true,
    ]);
}

/** SKUs the items index returns for the given query string. */
function skusFor($owner, $workspace, array $params): array
{
    $skus = [];

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', [$workspace, ...$params]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$skus) {
            $skus = collect($page->toArray()['props']['items']['data'])
                ->pluck('sku')
                ->sort()
                ->values()
                ->all();
        });

    return $skus;
}

test('the products status enum accepts the new stages', function () {
    // Guards the migration itself: MySQL silently rejects an out-of-enum value,
    // so a round-trip proves the column really was widened.
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach (Product::STATUSES as $status) {
        $product = Product::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'status' => $status,
        ]);

        expect($product->fresh()->status)->toBe($status);
    }

    expect(Product::STATUSES)->toContain('New', 'Maintaining');
});

test('the frontend product status list mirrors the PHP one', function () {
    $path = base_path('resources/js/constants/product-statuses.ts');
    expect($path)->toBeFile();

    preg_match(
        '/export const PRODUCT_STATUSES = \[(.*?)\] as const;/s',
        file_get_contents($path),
        $block,
    );

    preg_match_all("/'([^']+)'/", $block[1] ?? '', $matches);

    expect($matches[1])->toBe(Product::STATUSES);
});

test('the items list can be filtered by product status', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    itemOnProductStatus($workspace, $owner, 'SKU-NEW', 'New');
    itemOnProductStatus($workspace, $owner, 'SKU-SCALING', 'Scaling');
    itemOnProductStatus($workspace, $owner, 'SKU-MAINTAINING', 'Maintaining');

    expect(skusFor($owner, $workspace, []))
        ->toBe(['SKU-MAINTAINING', 'SKU-NEW', 'SKU-SCALING']);

    expect(skusFor($owner, $workspace, ['filter' => ['product_status' => 'Maintaining']]))
        ->toBe(['SKU-MAINTAINING']);

    expect(skusFor($owner, $workspace, ['filter' => ['product_status' => 'New']]))
        ->toBe(['SKU-NEW']);
});

test('filtering by product status excludes items with no product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    itemOnProductStatus($workspace, $owner, 'SKU-NEW', 'New');

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-ORPHAN',
        'is_active' => true,
    ]);

    // Unfiltered the orphan shows; filtering on a product attribute drops it.
    expect(skusFor($owner, $workspace, []))->toContain('SKU-ORPHAN');
    expect(skusFor($owner, $workspace, ['filter' => ['product_status' => 'New']]))
        ->toBe(['SKU-NEW']);
});

test('the product status filter also applies to the summarize view', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $scaling = itemOnProductStatus($workspace, $owner, 'SKU-CHILD-SCALING', 'Scaling');
    itemOnProductStatus($workspace, $owner, 'SKU-MAINTAINING', 'Maintaining');

    // Roll the scaling item up under a parent placeholder.
    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-PARENT',
        'is_active' => true,
        'is_parent' => true,
    ]);
    $scaling->update(['parent_id' => $parent->id]);

    $summarized = skusFor($owner, $workspace, [
        'summarize' => 1,
        'filter' => ['product_status' => 'Scaling'],
    ]);

    // The group survives on its child's product status, and shows as the parent.
    expect($summarized)->toBe(['SKU-PARENT']);

    expect(skusFor($owner, $workspace, [
        'summarize' => 1,
        'filter' => ['product_status' => 'Maintaining'],
    ]))->toBe(['SKU-MAINTAINING']);
});

test('an unknown product status yields no rows rather than erroring', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    itemOnProductStatus($workspace, $owner, 'SKU-NEW', 'New');

    expect(skusFor($owner, $workspace, ['filter' => ['product_status' => 'Nonsense']]))
        ->toBe([]);
});

test('the status column is still indexed for the filter to use', function () {
    // The filter reads products.status on a left join; the composite index is
    // what keeps that from degrading on large workspaces.
    $indexes = collect(DB::select('SHOW INDEX FROM products'))
        ->pluck('Column_name', 'Key_name');

    expect($indexes->keys())->toContain('idx_products_workspace_status');
});
