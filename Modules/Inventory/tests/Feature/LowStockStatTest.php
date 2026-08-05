<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Give an item a ledger stock via its latest transaction's running remaining_qty. */
function stockLedger(InventoryItem $item, int $remaining): void
{
    InventoryTransaction::create([
        'workspace_id' => $item->workspace_id,
        'inventory_item_id' => $item->id,
        'date' => '2026-06-01',
        'ref_no' => 'TXN-'.$item->id.'-'.$remaining,
        'remaining_qty' => $remaining,
    ]);
}

/** The dashboard's low-stock payload for a workspace. */
function lowStock($user, $workspace): array
{
    return test()->actingAs($user)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/low-stock")
        ->assertOk()
        ->json();
}

test('po needed is the buffer plus lead-time demand less stock on hand', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 4,
    ]);
    stockLedger($item, 12);

    $data = lowStock($owner, $workspace);

    // buffer 10 × 4 = 40, lead-time demand 5 × 4 = 20, on hand 12
    // -> 40 + 20 - 12 = 48.
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['sku'])->toBe('SKU-1')
        ->and($data['items'][0]['po_needed'])->toBe(48)
        ->and($data['listed_po_needed'])->toBe(48);
});

test('items are ranked by po needed, worst first', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Same buffer and lead time throughout, so the daily average sets the order.
    foreach ([['LOW', 1], ['HIGH', 9], ['MID', 5]] as [$sku, $average]) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'is_active' => true,
            'lead_time' => 5,
            'days_of_coverage' => 10,
            'three_days_average' => $average,
        ]);
    }

    $data = lowStock($owner, $workspace);

    expect(array_column($data['items'], 'sku'))->toBe(['HIGH', 'MID', 'LOW']);
});

test('a group recomputes po needed from its parts instead of summing children', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'GROUP',
        'is_parent' => true,
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
    ]);

    foreach ([['SUP-A', 3], ['SUP-B', 1]] as [$sku, $average]) {
        $child = InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => $sku,
            'parent_id' => $parent->id,
            'is_active' => true,
            'lead_time' => 5,
            'days_of_coverage' => 10,
            'three_days_average' => $average,
        ]);
        stockLedger($child, 10);
    }

    $data = lowStock($owner, $workspace);

    // One row, under the parent. The group's lead time and buffer are the
    // parent's (5 and 10), its average is the sum (4) and its stock is the sum
    // (20): 10 × 4 + 5 × 4 - 20 = 40. Summing the children's own figures would
    // have counted the lead-time demand twice and given a different number.
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['sku'])->toBe('GROUP')
        ->and($data['items'][0]['po_needed'])->toBe(40)
        ->and($data['items'][0]['is_group'])->toBeTrue()
        ->and($data['items'][0]['child_count'])->toBe(2);
});

test('incoming purchase-order stock is counted once, not twice', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 4,
    ]);
    stockLedger($item, 12);

    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'PO-1',
        'issue_date' => '2026-06-01',
        'status' => 6,
    ]);
    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => 10,
    ]);

    $data = lowStock($owner, $workspace);

    // 10 units are incoming, so on-hand-after-fulfilment is 12 + 10 = 22 and
    // po_needed is 40 + 20 - 22 = 38. Subtracting the incoming 10 a second time
    // would have given 28.
    expect($data['items'][0]['po_needed'])->toBe(38);
});

test('only the worst 20 groups are listed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach (range(1, 25) as $n) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => 'SKU-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'is_active' => true,
            'lead_time' => 1,
            'days_of_coverage' => 0,
            'three_days_average' => $n,
        ]);
    }

    $data = lowStock($owner, $workspace);

    expect($data['items'])->toHaveCount(20)
        ->and($data['limit'])->toBe(20)
        // 25 down to 6, at 1 day of lead time × the daily average.
        ->and($data['items'][0]['po_needed'])->toBe(25)
        ->and($data['items'][19]['po_needed'])->toBe(6);
});

test('covered items and inactive items are left out', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Well stocked against its demand -> nothing to order.
    $covered = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'COVERED',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 1,
    ]);
    stockLedger($covered, 500);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'RETIRED',
        'is_active' => false,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 9,
    ]);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SHORT',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 2,
    ]);

    $data = lowStock($owner, $workspace);

    expect(array_column($data['items'], 'sku'))->toBe(['SHORT']);
});

test('one workspace never sees another workspace items', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    InventoryItem::create([
        'workspace_id' => $workspaceB->id,
        'sku' => 'B-ONLY',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 10,
        'three_days_average' => 9,
    ]);

    $data = lowStock($owner, $workspaceA);

    expect($data['items'])->toBeEmpty()
        ->and($data['listed_po_needed'])->toBe(0);
});
