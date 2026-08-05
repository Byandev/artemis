<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** The dashboard's open purchase-order payload for a workspace. */
function openPos($user, $workspace): array
{
    return test()->actingAs($user)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/open-purchase-orders")
        ->assertOk()
        ->json();
}

test('each open line carries the date its order was issued', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'PO-1',
        'issue_date' => '2026-07-14',
        'expected_delivery_date' => '2026-07-28',
        'status' => 6,
    ]);
    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => 10,
    ]);

    $data = openPos($owner, $workspace);

    expect($data['lines'])->toHaveCount(1);
    // Date-only strings — the client formats them for display.
    expect($data['lines'][0]['issue_date'])->toBe('2026-07-14')
        ->and($data['lines'][0]['expected_delivery_date'])->toBe('2026-07-28')
        ->and($data['lines'][0]['control_no'])->toBe('PO-1')
        ->and($data['lines'][0]['waiting_qty'])->toBe(10);
});

test('each line carries what it has taken delivery of, for its progress ring', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'PO-1',
        'issue_date' => '2026-07-14',
        'status' => 6,
    ]);

    $line = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => 20,
    ]);
    $line->deliveries()->create(['qty' => 15, 'delivery_date' => '2026-07-20']);

    $data = openPos($owner, $workspace);

    // 15 of 20 -> the ring reads 75%, computed client-side from these two.
    expect($data['lines'][0]['ordered_qty'])->toBe(20)
        ->and($data['lines'][0]['delivered_qty'])->toBe(15)
        ->and($data['lines'][0]['waiting_qty'])->toBe(5);
});

test('lines are ordered by issue date, newest first, undated last', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    // Created out of order, so the sort cannot be passing by accident.
    foreach ([['MIDDLE', '2026-07-14'], ['OLDEST', '2026-05-02'], ['NEWEST', '2026-08-01']] as [$control, $issued]) {
        $order = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'control_no' => $control,
            'issue_date' => $issued,
            'status' => 6,
        ]);
        PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $order->id,
            'inventory_item_id' => $item->id,
            'count' => 5,
        ]);
    }

    $data = openPos($owner, $workspace);

    expect(array_column($data['lines'], 'control_no'))
        ->toBe(['NEWEST', 'MIDDLE', 'OLDEST']);
});
