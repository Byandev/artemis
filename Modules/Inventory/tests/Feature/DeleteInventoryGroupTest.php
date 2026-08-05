<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A parent placeholder with the given child SKUs grouped under it. */
function groupWith($workspace, string $parentSku, array $childSkus): InventoryItem
{
    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => $parentSku,
        'is_parent' => true,
        'is_active' => true,
    ]);

    foreach ($childSkus as $sku) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);
    }

    return $parent;
}

test('deleting a group removes the parent and ungroups its SKUs rather than deleting them', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = groupWith($workspace, 'GROUP', ['SUP-A', 'SUP-B']);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/inventory/items")
        ->delete("/workspaces/{$workspace->slug}/inventory/items/{$parent->id}")
        ->assertRedirect();

    expect(InventoryItem::find($parent->id))->toBeNull();

    // The children survive, standalone — their stock history is not collateral.
    $children = InventoryItem::whereIn('sku', ['SUP-A', 'SUP-B'])->get();
    expect($children)->toHaveCount(2)
        ->and($children->pluck('parent_id')->unique()->all())->toBe([null]);
});

test('deleting a plain item leaves other items alone', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $solo = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SOLO',
        'is_active' => true,
    ]);
    $other = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'OTHER',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/inventory/items/{$solo->id}")
        ->assertRedirect();

    expect(InventoryItem::find($solo->id))->toBeNull()
        ->and(InventoryItem::find($other->id))->not->toBeNull();
});

test('an item belonging to another workspace cannot be deleted', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    $foreign = InventoryItem::create([
        'workspace_id' => $workspaceB->id,
        'sku' => 'B-ONLY',
        'is_active' => true,
    ]);

    // Owner of A has the delete permission — but only for A's items.
    $this->actingAs($owner)
        ->delete("/workspaces/{$workspaceA->slug}/inventory/items/{$foreign->id}")
        ->assertNotFound();

    expect(InventoryItem::find($foreign->id))->not->toBeNull();
});
