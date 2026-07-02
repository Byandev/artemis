<?php

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
