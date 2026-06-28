<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;

uses(RefreshDatabase::class);

/** Read the computed waiting-for-delivery value for an item off the index page. */
function waitingFor(int $itemId, $owner, $workspace): ?int
{
    $value = null;

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($itemId, &$value) {
            $items = $page->toArray()['props']['items']['data'];
            $row = collect($items)->firstWhere('id', $itemId);
            $value = $row['waiting_for_delivery_stocks'];
        });

    return $value === null ? null : (int) $value;
}

test('waiting-for-delivery reflects the undelivered remainder on status-6 orders', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'issue_date' => '2026-06-01',
        'delivery_fee' => 0,
        'total_amount' => 0,
        'status' => 6, // Waiting For Delivery
    ]);

    $poItem = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 100,
        'amount' => 0,
        'total_amount' => 0,
    ]);

    // Nothing delivered yet → the full order is still waiting.
    expect(waitingFor($item->id, $owner, $workspace))->toBe(100);

    // Partial delivery of 60 → only the remaining 40 is still waiting.
    PurchasedOrderItemDelivery::create([
        'inventory_purchased_order_item_id' => $poItem->id,
        'delivery_date' => '2026-06-10',
        'qty' => 60,
    ]);
    expect(waitingFor($item->id, $owner, $workspace))->toBe(40);

    // Remaining 40 delivered → nothing left waiting (shown as "—" → null).
    PurchasedOrderItemDelivery::create([
        'inventory_purchased_order_item_id' => $poItem->id,
        'delivery_date' => '2026-06-12',
        'qty' => 40,
    ]);
    expect(waitingFor($item->id, $owner, $workspace))->toBeNull();
});

test('waiting-for-delivery ignores orders that are not in the awaiting-delivery status', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-2',
        'is_active' => true,
    ]);

    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'issue_date' => '2026-06-01',
        'delivery_fee' => 0,
        'total_amount' => 0,
        'status' => 2, // Approved — not awaiting delivery
    ]);

    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 50,
        'amount' => 0,
        'total_amount' => 0,
    ]);

    expect(waitingFor($item->id, $owner, $workspace))->toBeNull();
});
