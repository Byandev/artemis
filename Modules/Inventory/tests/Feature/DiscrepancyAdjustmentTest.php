<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemDiscrepancy;
use Modules\Inventory\Models\InventoryTransaction;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/** Read the inventory-items index row for an item (with its computed columns). */
function itemRow(int $itemId, $owner, $workspace): array
{
    $row = [];

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($itemId, &$row) {
            $items = $page->toArray()['props']['items']['data'];
            $row = collect($items)->firstWhere('id', $itemId) ?? [];
        });

    return $row;
}

/** Read the computed current_stocks (displayed remaining) for an item off the index page. */
function currentStocks(int $itemId, $owner, $workspace): ?int
{
    $value = itemRow($itemId, $owner, $workspace)['current_stocks'] ?? null;

    return $value === null ? null : (int) $value;
}

test('recording a count stores the signed discrepancy against the raw ledger and adjusts the displayed stock', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-DISC',
        'is_active' => true,
    ]);

    // Ledger says 100.
    InventoryTransaction::create([
        'workspace_id' => $workspace->id,
        'inventory_item_id' => $item->id,
        'date' => '2026-06-01',
        'ref_no' => 'T-1',
        'po_qty_in' => 0, 'po_qty_out' => 0, 'rts_goods_in' => 0,
        'rts_goods_out' => 0, 'rts_bad' => 0, 'lost' => 0,
        'remaining_qty' => 100,
    ]);

    expect(currentStocks($item->id, $owner, $workspace))->toBe(100);

    // Physical count of 95 → discrepancy = 95 - 100 = -5, displayed stock becomes 95.
    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.discrepancies.store', [$workspace, $item]), [
            'date' => '2026-06-15',
            'counted_qty' => 95,
        ])
        ->assertRedirect();

    $first = InventoryItemDiscrepancy::where('inventory_item_id', $item->id)->latest('id')->first();
    expect($first->discrepancy)->toBe(-5)
        ->and($first->counted_qty)->toBe(95);
    expect(currentStocks($item->id, $owner, $workspace))->toBe(95);

    // The list surfaces the offset + what was last counted and when.
    $row = itemRow($item->id, $owner, $workspace);
    expect((int) $row['discrepancy'])->toBe(-5)
        ->and((int) $row['discrepancy_counted_qty'])->toBe(95)
        ->and((string) $row['discrepancy_date'])->toContain('2026-06-15');

    // A later count of 90 wins. Critically the discrepancy is computed against the RAW
    // ledger (100), not the already-adjusted display (95): 90 - 100 = -10.
    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.discrepancies.store', [$workspace, $item]), [
            'date' => '2026-06-20',
            'counted_qty' => 90,
        ])
        ->assertRedirect();

    $second = InventoryItemDiscrepancy::where('inventory_item_id', $item->id)->latest('id')->first();
    expect($second->discrepancy)->toBe(-10);
    expect(currentStocks($item->id, $owner, $workspace))->toBe(90);
});

test('a backdated count is measured against the ledger stock as of that date, and carries forward', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-ASOF',
        'is_active' => true,
    ]);

    // Ledger: 100 on Jun 10, then 130 on Jun 20 (the current, newest level).
    InventoryTransaction::create([
        'workspace_id' => $workspace->id, 'inventory_item_id' => $item->id,
        'date' => '2026-06-10', 'ref_no' => 'A-1', 'po_qty_in' => 0, 'po_qty_out' => 0,
        'rts_goods_in' => 0, 'rts_goods_out' => 0, 'rts_bad' => 0, 'lost' => 0, 'remaining_qty' => 100,
    ]);
    InventoryTransaction::create([
        'workspace_id' => $workspace->id, 'inventory_item_id' => $item->id,
        'date' => '2026-06-20', 'ref_no' => 'A-2', 'po_qty_in' => 0, 'po_qty_out' => 0,
        'rts_goods_in' => 0, 'rts_goods_out' => 0, 'rts_bad' => 0, 'lost' => 0, 'remaining_qty' => 130,
    ]);

    // stock-as-of endpoint: before any txn → null; Jun 15 sees the Jun 10 level; Jun 20+ sees 130.
    $asOf = fn (string $d) => test()->actingAs($owner)
        ->getJson(route('workspaces.inventory.item.stock-as-of', [$workspace, $item]).'?date='.$d)
        ->json('remaining_qty');

    expect($asOf('2026-06-05'))->toBeNull()
        ->and($asOf('2026-06-15'))->toBe(100)
        ->and($asOf('2026-06-25'))->toBe(130);

    // Count of 95 recorded for Jun 15 → measured vs the Jun 10 level (100): discrepancy -5.
    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.discrepancies.store', [$workspace, $item]), [
            'date' => '2026-06-15',
            'counted_qty' => 95,
        ])
        ->assertRedirect();

    expect(InventoryItemDiscrepancy::where('inventory_item_id', $item->id)->latest('id')->first()->discrepancy)->toBe(-5);

    // The -5 offset carries forward onto the current level: 130 + (-5) = 125.
    expect(currentStocks($item->id, $owner, $workspace))->toBe(125);
});

test('an item with no ledger shows dash, and a count on it reads back as the counted quantity', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-EMPTY',
        'is_active' => true,
    ]);

    // No transactions and no count → displayed as "—" (null).
    expect(currentStocks($item->id, $owner, $workspace))->toBeNull();

    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.discrepancies.store', [$workspace, $item]), [
            'date' => '2026-06-15',
            'counted_qty' => 42,
        ])
        ->assertRedirect();

    // Raw ledger 0 + discrepancy (42 - 0) = 42.
    $disc = InventoryItemDiscrepancy::where('inventory_item_id', $item->id)->latest('id')->first();
    expect($disc->discrepancy)->toBe(42);
    expect(currentStocks($item->id, $owner, $workspace))->toBe(42);
});
