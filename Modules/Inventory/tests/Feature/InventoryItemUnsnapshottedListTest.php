<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The list is anchored on saved items, not on the snapshot table: an item created
 * after the day's snapshot ran still has to appear, with null metrics the frontend
 * renders as "—". Before this, a freshly synced item was invisible until the next
 * nightly run.
 */

/** Give an item a ledger stock via its latest transaction's running remaining_qty. */
function stock(InventoryItem $item, int $remaining): void
{
    InventoryTransaction::create([
        'workspace_id' => $item->workspace_id,
        'inventory_item_id' => $item->id,
        'date' => '2026-08-01',
        'ref_no' => 'TXN-'.$item->id,
        'remaining_qty' => $remaining,
    ]);
}

/** The list's rows for a query string, keyed by SKU. */
function rowsBySku($owner, $workspace, string $qs = ''): array
{
    $rows = [];

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).$qs)
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows) {
            $rows = collect($page->toArray()['props']['items']['data'])
                ->keyBy('sku')
                ->all();
        });

    return $rows;
}

/** A workspace holding one snapshotted item plus one created after the snapshot. */
function workspaceWithLateItem(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $snapshotted = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-OLD',
        'is_active' => true,
        'lead_time' => 5,
        'three_days_average' => 4,
    ]);
    stock($snapshotted, 30);

    test()->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true])
        ->assertExitCode(0);

    // Arrives after the snapshot — this is the ERP sync's newly created item.
    $late = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-NEW',
        'is_active' => true,
    ]);

    return ['owner' => $owner, 'workspace' => $workspace, 'snapshotted' => $snapshotted, 'late' => $late];
}

test('an item created after the snapshot still lists, with dashes for its metrics', function () {
    ['owner' => $owner, 'workspace' => $workspace] = workspaceWithLateItem();

    $rows = rowsBySku($owner, $workspace, '?summarize=0');

    expect($rows)->toHaveKeys(['SKU-OLD', 'SKU-NEW']);

    // Nulls, not zeroes: the day recorded no figure for this item, and a 0 would
    // read as "it had no stock" rather than "we did not measure it".
    foreach (['current_stocks', 'remaining_after_fulfillment', 'three_days_average', 'po_needed', 'days_it_can_last'] as $column) {
        expect($rows['SKU-NEW'][$column])->toBeNull();
    }

    // The snapshotted item still reports the day's frozen figures.
    expect($rows['SKU-OLD']['current_stocks'])->toBe(30);
});

test('the rolled-up view lists it too, and does not fake a zero for the group', function () {
    ['owner' => $owner, 'workspace' => $workspace] = workspaceWithLateItem();

    $rows = rowsBySku($owner, $workspace);

    expect($rows)->toHaveKeys(['SKU-OLD', 'SKU-NEW'])
        ->and($rows['SKU-NEW']['current_stocks'])->toBeNull()
        ->and($rows['SKU-NEW']['days_it_can_last'])->toBeNull()
        ->and($rows['SKU-OLD']['current_stocks'])->toEqual(30);
});

test('the new item carries its own identity and lead time, so it can be edited', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'late' => $late] = workspaceWithLateItem();

    $late->update(['lead_time' => 7]);

    $rows = rowsBySku($owner, $workspace, '?summarize=0');

    expect($rows['SKU-NEW']['id'])->toBe($late->id)
        ->and($rows['SKU-NEW']['is_active'])->toBeTrue()
        ->and((int) $rows['SKU-NEW']['lead_time'])->toBe(7);
});

test('searching and the active filter still reach the unsnapshotted item', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'late' => $late] = workspaceWithLateItem();

    expect(rowsBySku($owner, $workspace, '?summarize=0&filter[search]=SKU-NEW'))->toHaveKey('SKU-NEW');

    $late->update(['is_active' => false]);

    expect(rowsBySku($owner, $workspace, '?summarize=0'))->not->toHaveKey('SKU-NEW')
        ->and(rowsBySku($owner, $workspace, '?summarize=0&filter[is_active]=all'))->toHaveKey('SKU-NEW');
});
