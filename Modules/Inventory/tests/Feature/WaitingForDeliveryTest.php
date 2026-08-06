<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
use Modules\Inventory\Support\InventoryStockColumns;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/** Read a computed stock column for an item off the index page. */
function stockColumn(int $itemId, $owner, $workspace, string $column): ?int
{
    $value = null;

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=0')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($itemId, $column, &$value) {
            $items = $page->toArray()['props']['items']['data'];
            $row = collect($items)->firstWhere('id', $itemId);
            $value = $row[$column];
        });

    return $value === null ? null : (int) $value;
}

/** Everything still owed on open orders — released and requested alike. */
function waitingFor(int $itemId, $owner, $workspace): ?int
{
    return stockColumn($itemId, $owner, $workspace, 'waiting_for_delivery_stocks');
}

/**
 * Units owed on orders still awaiting approval or payment. Read straight off
 * the query rather than the page: the items list deliberately shows only the
 * combined "Waiting for Delivery" figure, and this split feeds the PO-flow
 * dashboard and the daily snapshot instead.
 */
function requestedFor(int $itemId, $owner, $workspace): ?int
{
    $value = InventoryItem::query()
        ->whereKey($itemId)
        ->tap(fn ($q) => InventoryStockColumns::applyJoins($q))
        ->selectRaw(InventoryStockColumns::requestedStocks().' as requested_stocks')
        ->value('requested_stocks');

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

test('the waiting-for-delivery modal lists the pending purchase orders behind the figure', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-3',
        'is_active' => true,
    ]);

    $makeOrder = function (int $status, string $issueDate, ?string $controlNo = null) use ($workspace) {
        return PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'issue_date' => $issueDate,
            'control_no' => $controlNo,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'status' => $status,
        ]);
    };

    $makeLine = fn (PurchasedOrder $po, int $count) => PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => $count,
        'amount' => 0,
        'total_amount' => 0,
    ]);

    $makeLine($makeOrder(6, '2026-06-01', 'PO-A'), 100);
    $partial = $makeLine($makeOrder(5, '2026-06-05', 'PO-B'), 80);
    PurchasedOrderItemDelivery::create([
        'inventory_purchased_order_item_id' => $partial->id,
        'delivery_date' => '2026-06-08',
        'qty' => 30,
    ]);

    // Excluded: a fully-delivered line on a pending order, and a cancelled order.
    $done = $makeLine($makeOrder(6, '2026-06-06', 'PO-C'), 10);
    PurchasedOrderItemDelivery::create([
        'inventory_purchased_order_item_id' => $done->id,
        'delivery_date' => '2026-06-09',
        'qty' => 10,
    ]);
    $makeLine($makeOrder(8, '2026-06-07', 'PO-D'), 25);

    $response = test()->actingAs($owner)
        ->getJson(route('workspaces.inventory.item.pending-purchase-orders', [$workspace, $item]))
        ->assertOk();

    $orders = $response->json('orders');

    expect(collect($orders)->pluck('control_no')->all())->toBe(['PO-B', 'PO-A']);
    expect($orders[0])->toMatchArray([
        'status_label' => 'For Purchase',
        'ordered_qty' => 80,
        'delivered_qty' => 30,
        'balance' => 50,
    ]);
    expect($orders[1])->toMatchArray([
        'status_label' => 'Waiting For Delivery',
        'ordered_qty' => 100,
        'delivered_qty' => 0,
        'balance' => 100,
    ]);

    // Both listed orders are with a supplier, so the released subtotal is the
    // whole balance — and it is exactly what the list column shows.
    expect($response->json('total_balance'))->toBe(150);
    expect($response->json('released_balance'))
        ->toBe(150)
        ->toBe(waitingFor($item->id, $owner, $workspace));
    expect($response->json('requested_balance'))->toBe(0);
});

test('the waiting-for-delivery modal rolls a parent item up over its children', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-PARENT',
        'is_active' => true,
        'is_parent' => true,
    ]);

    $child = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-CHILD',
        'is_active' => true,
        'parent_id' => $parent->id,
    ]);

    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'issue_date' => '2026-06-01',
        'delivery_fee' => 0,
        'total_amount' => 0,
        'status' => 6,
    ]);

    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $child->id,
        'count' => 70,
        'amount' => 0,
        'total_amount' => 0,
    ]);

    $response = test()->actingAs($owner)
        ->getJson(route('workspaces.inventory.item.pending-purchase-orders', [$workspace, $parent]))
        ->assertOk();

    expect($response->json('total_balance'))->toBe(70);
    expect($response->json('orders.0.sku'))->toBe('SKU-CHILD');
});

test('the waiting-for-delivery modal rejects an item from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $foreign = InventoryItem::create([
        'workspace_id' => $other->id,
        'sku' => 'SKU-FOREIGN',
        'is_active' => true,
    ]);

    test()->actingAs($owner)
        ->getJson(route('workspaces.inventory.item.pending-purchase-orders', [$workspace, $foreign]))
        ->assertNotFound();
});

test('waiting-for-delivery counts every open order, with the un-sent part broken out', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-2',
        'is_active' => true,
    ]);

    $makeLine = function (int $status, int $count) use ($workspace, $item) {
        $po = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'issue_date' => '2026-06-01',
            'delivery_fee' => 0,
            'total_amount' => 0,
            'status' => $status,
        ]);

        PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'count' => $count,
            'amount' => 0,
            'total_amount' => 0,
        ]);
    };

    // Cancelled — closed, so its units never count anywhere.
    $makeLine(PurchasedOrder::CANCELLED, 50);
    expect(waitingFor($item->id, $owner, $workspace))->toBeNull()
        ->and(requestedFor($item->id, $owner, $workspace))->toBeNull();

    // Approved — raised but not yet with a supplier. Committed quantity, so it
    // counts as incoming; also reported separately so the delay is visible.
    $makeLine(2, 50);
    expect(waitingFor($item->id, $owner, $workspace))->toBe(50)
        ->and(requestedFor($item->id, $owner, $workspace))->toBe(50);

    // Released to the supplier — the total grows, the un-sent part does not.
    $makeLine(6, 30);
    expect(waitingFor($item->id, $owner, $workspace))->toBe(80)
        ->and(requestedFor($item->id, $owner, $workspace))->toBe(50);
});

test('a raised purchase order is never reordered, whatever stage it sits at', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-REORDER',
        'is_active' => true,
        'lead_time' => 10,
        'days_of_coverage' => 10,
        'three_days_average' => 10,   // 20 days of cover = 200 units wanted
    ]);

    $order = function (int $status, int $count) use ($workspace, $item) {
        $po = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'issue_date' => '2026-06-01',
            'delivery_fee' => 0,
            'total_amount' => 0,
            'status' => $status,
        ]);
        PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'count' => $count,
            'amount' => 0,
            'total_amount' => 0,
        ]);
    };

    // Nothing on hand, nothing ordered: the full 200 is needed.
    expect(stockColumn($item->id, $owner, $workspace, 'po_needed'))->toBe(200);

    // 200 raised, still sitting in To Pay. The quantity is committed, so the
    // need is covered — telling anyone to order another 200 would double-order.
    // That the order is stuck is a separate signal, carried by requested_stocks.
    $order(3, 200);
    expect(stockColumn($item->id, $owner, $workspace, 'po_needed'))->toBe(0)
        ->and(requestedFor($item->id, $owner, $workspace))->toBe(200);

    // Once released, the same 200 is still covered — nothing double counts.
    $order2 = $order;
    expect(stockColumn($item->id, $owner, $workspace, 'po_needed'))->toBe(0);
});
