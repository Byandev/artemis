<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** The dashboard's high-unfulfilled payload for a workspace. */
function highUnfulfilled($user, $workspace): array
{
    return test()->actingAs($user)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/high-unfulfilled")
        ->assertOk()
        ->json();
}

test('items are ranked by unfulfilled units, worst first', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach ([['LOW', 3], ['HIGH', 90], ['MID', 20]] as [$sku, $unfulfilled]) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'is_active' => true,
            'unfulfilled_count' => $unfulfilled,
        ]);
    }

    $data = highUnfulfilled($owner, $workspace);

    expect(array_column($data['items'], 'sku'))->toBe(['HIGH', 'MID', 'LOW'])
        ->and($data['items'][0]['unfulfilled_count'])->toBe(90)
        ->and($data['listed_unfulfilled'])->toBe(113);
});

test('children roll into their parent as a single group row', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'GROUP',
        'is_parent' => true,
        'is_active' => true,
    ]);

    foreach ([['SUP-A', 15], ['SUP-B', 25]] as [$sku, $unfulfilled]) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'parent_id' => $parent->id,
            'is_active' => true,
            'unfulfilled_count' => $unfulfilled,
        ]);
    }

    $data = highUnfulfilled($owner, $workspace);

    // One row for the group, under the parent's SKU, carrying the summed count —
    // not two rows of 15 and 25.
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['sku'])->toBe('GROUP')
        ->and($data['items'][0]['unfulfilled_count'])->toBe(40)
        ->and($data['items'][0]['is_group'])->toBeTrue()
        ->and($data['items'][0]['child_count'])->toBe(2);
});

test('a standalone item reports as a group of one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SOLO',
        'is_active' => true,
        'unfulfilled_count' => 5,
    ]);

    $data = highUnfulfilled($owner, $workspace);

    expect($data['items'][0]['is_group'])->toBeFalse()
        ->and($data['items'][0]['child_count'])->toBe(1);
});

test('only the worst 20 groups are listed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // 25 items, each owing more than the last.
    foreach (range(1, 25) as $n) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => 'SKU-'.$n,
            'is_active' => true,
            'unfulfilled_count' => $n,
        ]);
    }

    $data = highUnfulfilled($owner, $workspace);

    expect($data['items'])->toHaveCount(20)
        ->and($data['limit'])->toBe(20)
        // The top of the list, and the cut-off: 25 down to 6.
        ->and($data['items'][0]['unfulfilled_count'])->toBe(25)
        ->and($data['items'][19]['unfulfilled_count'])->toBe(6);
});

test('items owing nothing and inactive items are left out', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SETTLED',
        'is_active' => true,
        'unfulfilled_count' => 0,
    ]);
    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'RETIRED',
        'is_active' => false,
        'unfulfilled_count' => 40,
    ]);
    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'OWING',
        'is_active' => true,
        'unfulfilled_count' => 7,
    ]);

    $data = highUnfulfilled($owner, $workspace);

    expect(array_column($data['items'], 'sku'))->toBe(['OWING']);
});

test('one workspace never sees another workspace items', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    InventoryItem::create([
        'workspace_id' => $workspaceB->id,
        'sku' => 'B-ONLY',
        'is_active' => true,
        'unfulfilled_count' => 99,
    ]);

    $data = highUnfulfilled($owner, $workspaceA);

    expect($data['items'])->toBeEmpty()
        ->and($data['listed_unfulfilled'])->toBe(0);
});
