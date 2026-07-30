<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/** A PO with one line item for `count` units, in the given status. */
function makeOrderWithLine($workspace, int $status, int $count, ?string $expectedDate = null): PurchasedOrder
{
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-'.uniqid(),
        'is_active' => true,
    ]);

    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'issue_date' => '2026-06-01',
        'expected_delivery_date' => $expectedDate,
        'delivery_fee' => 0,
        'total_amount' => 0,
        'status' => $status,
    ]);

    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => $count,
        'amount' => 0,
        'total_amount' => 0,
    ]);

    return $order;
}

function deliver(PurchasedOrder $order, int $qty): void
{
    PurchasedOrderItemDelivery::create([
        'inventory_purchased_order_item_id' => $order->items()->first()->id,
        'delivery_date' => '2026-06-10',
        'qty' => $qty,
    ]);
}

describe('status constants', function () {
    test('closed and awaiting statuses partition the full status list', function () {
        $all = array_keys(PurchasedOrder::STATUSES);
        $partitioned = array_merge(
            PurchasedOrder::AWAITING_DELIVERY_STATUSES,
            PurchasedOrder::CLOSED_STATUSES,
        );

        sort($all);
        sort($partitioned);

        // Every status belongs to exactly one bucket — a new status added to
        // STATUSES without being classified would silently fall through the
        // incoming-stock and dashboard queries.
        expect($partitioned)->toBe($all);
    });

    test('the frontend status map mirrors the PHP one', function () {
        $path = base_path('resources/js/constants/purchased-order-statuses.ts');
        expect($path)->toBeFile();

        preg_match_all(
            "/^\s{4}(\d+): \{\s*\n\s*label: '([^']+)'/m",
            file_get_contents($path),
            $matches,
            PREG_SET_ORDER,
        );

        $fromTs = collect($matches)
            ->mapWithKeys(fn ($m) => [(int) $m[1] => $m[2]])
            ->all();

        expect($fromTs)->toBe(PurchasedOrder::STATUSES);
    });
});

describe('delivery timeliness', function () {
    test('an order past its expected date with stock still owed is delayed', function () {
        ['workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100, now()->subDay()->toDateString());
        deliver($order, 40);

        expect($order->fresh()->load('items.deliveries')->delivery_timeliness)->toBe('delayed');
    });

    test('a fully delivered order is not marked late once its expected date passes', function () {
        ['workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100, now()->subDay()->toDateString());
        deliver($order, 100);

        expect($order->fresh()->load('items.deliveries')->delivery_timeliness)->toBe('ontime');
    });

    test('an order with no expected date has no timeliness', function () {
        ['workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100);

        expect($order->fresh()->load('items.deliveries')->delivery_timeliness)->toBeNull();
    });
});

describe('closing a purchase order', function () {
    test('closing an order that still owes stock is rejected', function () {
        ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100);
        deliver($order, 40);

        test()->actingAs($owner)
            ->put(route('workspaces.inventory.po-monitoring.orders.status', [$workspace, $order]), [
                'status' => PurchasedOrder::DELIVERED,
            ])
            ->assertSessionHasErrors('status');

        expect($order->fresh()->status)->toBe(6);
    });

    test('closing short succeeds when the user confirms', function () {
        ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100);
        deliver($order, 40);

        test()->actingAs($owner)
            ->put(route('workspaces.inventory.po-monitoring.orders.status', [$workspace, $order]), [
                'status' => PurchasedOrder::CANCELLED,
                'force' => true,
            ])
            ->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(PurchasedOrder::CANCELLED);
    });

    test('closing a fully delivered order needs no confirmation', function () {
        ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 6, 100);
        deliver($order, 100);

        test()->actingAs($owner)
            ->put(route('workspaces.inventory.po-monitoring.orders.status', [$workspace, $order]), [
                'status' => PurchasedOrder::DELIVERED,
            ])
            ->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(PurchasedOrder::DELIVERED);
    });

    test('moving between open statuses is never blocked', function () {
        ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

        $order = makeOrderWithLine($workspace, 1, 100);

        test()->actingAs($owner)
            ->put(route('workspaces.inventory.po-monitoring.orders.status', [$workspace, $order]), [
                'status' => 4,
            ])
            ->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(4);
    });
});

test('the index can be filtered by status', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    makeOrderWithLine($workspace, 4, 10);
    makeOrderWithLine($workspace, 6, 20);
    makeOrderWithLine($workspace, 6, 30);

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.purchased-orders.index', [$workspace, 'filter' => ['status' => 6]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('orders.total', 2));
});
