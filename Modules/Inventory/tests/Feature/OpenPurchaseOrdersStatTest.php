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
        'status' => 6,
    ]);
    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => 10,
    ]);

    $data = openPos($owner, $workspace);

    expect($data['lines'])->toHaveCount(1);
    // A date-only string — the client formats it for display.
    expect($data['lines'][0]['issue_date'])->toBe('2026-07-14')
        ->and($data['lines'][0]['control_no'])->toBe('PO-1')
        ->and($data['lines'][0]['waiting_qty'])->toBe(10);
});
