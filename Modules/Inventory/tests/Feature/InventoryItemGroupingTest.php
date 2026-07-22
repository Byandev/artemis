<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Give an item a ledger stock by recording its latest transaction remaining_qty. */
function stockItem(InventoryItem $item, int $remaining): void
{
    InventoryTransaction::create([
        'workspace_id' => $item->workspace_id,
        'inventory_item_id' => $item->id,
        'date' => '2026-06-01',
        'ref_no' => 'TXN-'.$item->id,
        'remaining_qty' => $remaining,
    ]);
}

test('bulkGroup creates a parent and assigns the selected items to it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$a->id, $b->id],
            'new_parent_sku' => 'WIDGET-GROUP',
        ])
        ->assertRedirect();

    $parent = InventoryItem::where('workspace_id', $workspace->id)
        ->where('sku', 'WIDGET-GROUP')
        ->first();

    expect($parent)->not->toBeNull();
    expect($parent->is_parent)->toBeTrue();
    expect($a->fresh()->parent_id)->toBe($parent->id);
    expect($b->fresh()->parent_id)->toBe($parent->id);
});

test('bulkGroup with no parent info ungroups the selected items', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'P', 'is_parent' => true, 'is_active' => true]);
    $child = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'C', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$child->id],
        ])
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('summarize view rolls children up under the parent and sums their stock', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    stockItem($a, 30);
    stockItem($b, 12);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=1')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parent) {
            $rows = collect($page->toArray()['props']['items']['data']);

            // The two child SKUs collapse into the single parent row.
            expect($rows)->toHaveCount(1);

            $row = $rows->first();
            expect((int) $row['id'])->toBe($parent->id);
            expect($row['sku'])->toBe('GROUP');
            expect((int) $row['is_group'])->toBe(1);
            expect((int) $row['child_count'])->toBe(2);
            expect((int) $row['current_stocks'])->toBe(42);
        });
});

test('summarize view keeps the parent id/sku for a team-scoped view even though the parent has no product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    // Build a parent group whose single child's product is visible to $team.
    $groupForTeam = function (Team $team, string $parentSku, string $childSku, int $stock) use ($workspace) {
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        $shop = Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);
        $shop->teams()->attach($team->id);

        // The parent placeholder has no product of its own.
        $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => $parentSku, 'is_parent' => true, 'is_active' => true]);
        $child = InventoryItem::create([
            'workspace_id' => $workspace->id, 'product_id' => $product->id,
            'sku' => $childSku, 'parent_id' => $parent->id, 'is_active' => true,
        ]);
        stockItem($child, $stock);

        return $parent;
    };

    $parentA = $groupForTeam($teamA, 'GROUP-A', 'SUP-A', 20);
    $parentB = $groupForTeam($teamB, 'GROUP-B', 'SUP-B', 99);

    // Owner "viewing as" team A -> team-scoped visibility applies. Before the fix the
    // no-product parent was filtered out and the row fell back to the child's id.
    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace)."?summarize=1&team_id={$teamA->id}")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parentA, $parentB) {
            $rows = collect($page->toArray()['props']['items']['data']);

            // Only team A's group is visible — team B's must not leak.
            expect($rows)->toHaveCount(1);
            expect($rows->pluck('id')->map('intval'))->not->toContain($parentB->id);

            $row = $rows->first();
            expect((int) $row['id'])->toBe($parentA->id);
            expect($row['sku'])->toBe('GROUP-A');
            expect((int) $row['is_group'])->toBe(1);
            expect((int) $row['child_count'])->toBe(1);
            expect((int) $row['current_stocks'])->toBe(20);
        });
});

test('flat view lists the individual child SKUs, tagged with their parent', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($a) {
            $rows = collect($page->toArray()['props']['items']['data']);
            $child = $rows->firstWhere('id', $a->id);

            expect($child)->not->toBeNull();
            expect($child['parent_sku'])->toBe('GROUP');
        });
});

test('the n8n keywords endpoint excludes parent items', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // A parent and a child, both with keywords.
    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true, 'sales_keywords' => 'parent-kw']);
    $child = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'is_active' => true, 'sales_keywords' => 'child-kw']);

    $response = $this->getJson('/api/v1/public/inventory-items/keywords', [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    $ids = collect($response->json())->pluck('inventory_item_id');

    expect($ids)->toContain($child->id);
    expect($ids)->not->toContain($parent->id);
});
