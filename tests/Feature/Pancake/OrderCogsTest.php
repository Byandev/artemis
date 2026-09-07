<?php

use Illuminate\Support\Str;
use Modules\Pancake\Actions\SyncOrderItemsAction;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderItem;

/**
 * Cost of goods on a pancake order line, written by the sync from the
 * variation's import price times the quantity sold.
 *
 * The rule that runs through all of it is that null is not zero — a line nobody
 * has costed must not read as one whose goods were free, so a payload with no
 * price leaves the column alone rather than writing a zero into it.
 */
function cogsOrder(int $workspaceId, array $attrs = []): Order
{
    return Order::create(array_merge([
        'workspace_id' => $workspaceId,
        'order_number' => fake()->unique()->numerify('PC-#####'),
        'status' => 3,
        'status_name' => 'delivered',
        'shop_id' => 1,
        'page_id' => 1,
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => '2026-05-01 09:00:00',
        'final_amount' => 1000,
    ], $attrs));
}

function cogsItem(Order $order, ?float $cogs, int $quantity = 1): OrderItem
{
    return OrderItem::create([
        'order_id' => $order->id,
        'pancake_id' => (string) Str::uuid(),
        'pancake_order_id' => $order->order_number,
        'quantity' => $quantity,
        'cogs' => $cogs,
    ]);
}

beforeEach(function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => false]);

    $this->workspace = $workspace;
});

it('leaves a recorded cost alone when the sync re-writes its line', function () {
    $order = cogsOrder($this->workspace->id);

    $pancakeId = (string) Str::uuid();
    $productId = (string) Str::uuid();
    $variantId = (string) Str::uuid();

    $item = OrderItem::create([
        'order_id' => $order->id,
        'pancake_id' => $pancakeId,
        'pancake_order_id' => $order->order_number,
        'pancake_product_id' => $productId,
        'pancake_variant_id' => $variantId,
        'quantity' => 1,
        'cogs' => 400,
    ]);

    // The payload names no import price for this variation, so the re-sync must
    // leave the cost on record alone rather than blanking it.
    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'items' => [[
            'id' => $pancakeId,
            'product_id' => $productId,
            'variation_id' => $variantId,
            'quantity' => 3,
            'variation_info' => ['display_id' => 'SKU-1'],
        ]],
    ]);

    expect((float) $item->fresh()->cogs)->toBe(400.0)
        ->and((int) $item->fresh()->quantity)->toBe(3);
});

/** One payload line, with an optional import price on its variation. */
function syncItem(array $overrides = []): array
{
    return array_merge([
        'id' => (string) Str::uuid(),
        'product_id' => (string) Str::uuid(),
        'variation_id' => (string) Str::uuid(),
        'quantity' => 1,
        'variation_info' => ['display_id' => 'SKU-1'],
    ], $overrides);
}

it('costs a line from the variation import price times its quantity', function () {
    $order = cogsOrder($this->workspace->id);

    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'order_currency' => 'PHP',
        'items' => [
            syncItem(['quantity' => 3, 'variation_info' => ['display_id' => 'A', 'last_imported_price' => 120]]),
            syncItem(['quantity' => 2, 'variation_info' => ['display_id' => 'B', 'last_imported_price' => 55.5]]),
        ],
    ]);

    // 3 x 120 = 360, 2 x 55.50 = 111.
    expect((float) $order->items()->sum('cogs'))->toBe(471.0);
});

it('unscales the import price by the order currency', function () {
    $order = cogsOrder($this->workspace->id);

    // "PHP100" means every amount in the payload is multiplied by 100 — the same
    // scaling final_amount is read through on the order itself.
    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'order_currency' => 'PHP100',
        'items' => [
            syncItem(['quantity' => 2, 'variation_info' => ['display_id' => 'A', 'last_imported_price' => 25000]]),
        ],
    ]);

    // 25000/100 = 250 a unit, two of them.
    expect((float) $order->items()->sum('cogs'))->toBe(500.0);
});

it('treats a missing currency code as unscaled', function () {
    $order = cogsOrder($this->workspace->id);

    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'items' => [
            syncItem(['quantity' => 1, 'variation_info' => ['display_id' => 'A', 'last_imported_price' => 300]]),
        ],
    ]);

    expect((float) $order->items()->sum('cogs'))->toBe(300.0);
});

it('records a genuine zero import price rather than skipping the line', function () {
    $order = cogsOrder($this->workspace->id);

    // A free item is a cost of zero, which is a figure — unlike a missing price,
    // which is not.
    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'order_currency' => 'PHP',
        'items' => [
            syncItem(['quantity' => 1, 'variation_info' => ['display_id' => 'A', 'last_imported_price' => 0]]),
        ],
    ]);

    expect((float) $order->items()->value('cogs'))->toBe(0.0);
});

it('costs a line for no units at nothing', function () {
    $order = cogsOrder($this->workspace->id);

    // Price times quantity, taken literally — no units bought, nothing spent.
    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'order_currency' => 'PHP',
        'items' => [
            syncItem(['quantity' => 0, 'variation_info' => ['display_id' => 'A', 'last_imported_price' => 90]]),
        ],
    ]);

    expect((float) $order->items()->value('cogs'))->toBe(0.0);
});

it('leaves the line uncosted when the variation has no import price', function () {
    $order = cogsOrder($this->workspace->id);

    (new SyncOrderItemsAction)->execute($order, [
        'id' => $order->order_number,
        'order_currency' => 'PHP',
        'items' => [syncItem(['quantity' => 4])],
    ]);

    expect($order->items()->value('cogs'))->toBeNull();
});
