<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the edit endpoint returns the record own values, not a group roll-up', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'GROUP',
        'is_parent' => true,
        'is_active' => true,
        'lead_time' => 5,
    ]);

    // Children carry the stock; the summarize row would report their sums.
    foreach ([['SUP-A', 30], ['SUP-B', 12]] as [$sku, $unfulfilled]) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'parent_id' => $parent->id,
            'is_active' => true,
            'unfulfilled_count' => $unfulfilled,
            'three_days_average' => 4,
        ]);
    }

    $data = $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/inventory/items/{$parent->id}/edit")
        ->assertOk()
        ->json('item');

    // The parent's own values — 0, not the group's 42 and 8. Feeding the form
    // the roll-up would write those totals onto the parent on save.
    expect($data['sku'])->toBe('GROUP')
        ->and($data['is_parent'])->toBeTrue()
        ->and($data['lead_time'])->toBe(5)
        ->and($data['unfulfilled_count'])->toBe(0)
        // Cast: a whole-number float survives the JSON round trip as an int.
        ->and((float) $data['three_days_average'])->toBe(0.0);
});

test('a parent can be renamed and re-dated through the normal update route', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'GROUP',
        'is_parent' => true,
        'is_active' => true,
        'lead_time' => 5,
    ]);
    $child = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SUP-A',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/inventory/items/{$parent->id}", [
            'sku' => 'GROUP-RENAMED',
            'is_active' => true,
            'lead_time' => 9,
        ])
        ->assertRedirect();

    $parent->refresh();

    expect($parent->sku)->toBe('GROUP-RENAMED')
        ->and($parent->lead_time)->toBe(9)
        // Still a parent, and still holding its child.
        ->and($parent->is_parent)->toBeTrue()
        ->and($child->fresh()->parent_id)->toBe($parent->id);
});

test('the edit endpoint refuses an item from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    $foreign = InventoryItem::create([
        'workspace_id' => $workspaceB->id,
        'sku' => 'B-ONLY',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspaceA->slug}/inventory/items/{$foreign->id}/edit")
        ->assertNotFound();
});

test('update refuses an item from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    $foreign = InventoryItem::create([
        'workspace_id' => $workspaceB->id,
        'sku' => 'B-ONLY',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/inventory/items/{$foreign->id}", [
            'sku' => 'HIJACKED',
            'is_active' => true,
        ])
        ->assertNotFound();

    expect($foreign->fresh()->sku)->toBe('B-ONLY');
});
