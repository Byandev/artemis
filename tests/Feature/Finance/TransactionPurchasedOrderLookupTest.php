<?php

use Modules\Finance\Models\TransactionType;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Products\Models\Product;

function polUrl($workspace, array $query = []): string
{
    return "/workspaces/{$workspace->slug}/finance/purchased-orders".
        ($query ? '?'.http_build_query($query) : '');
}

/** A catalog product of the workspace, owned by the given user. */
function polProduct($workspace, $owner, string $name): Product
{
    return Product::factory()->create([
        'workspace_id' => $workspace->id,
        'owner_id' => $owner->id,
        'name' => $name,
        'title' => $name,
    ]);
}

/** An inventory SKU, optionally belonging to a catalog product. */
function polItem($workspace, string $sku, ?Product $product = null): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product?->id,
        'sku' => $sku,
        'is_active' => true,
    ]);
}

/** A purchase order carrying `[InventoryItem => quantity]`. */
function polOrder($workspace, string $deliveryNo, array $lines, array $attrs = []): PurchasedOrder
{
    $order = PurchasedOrder::create(array_merge([
        'workspace_id' => $workspace->id,
        'delivery_no' => $deliveryNo,
        'issue_date' => '2026-07-14',
        'delivery_fee' => 1500,
        'total_amount' => 50000,
        'status' => 6,
    ], $attrs));

    foreach ($lines as $line) {
        PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $order->id,
            'inventory_item_id' => $line[0]->id,
            'count' => $line[1],
        ]);
    }

    return $order;
}

test('an order carries the quantity of each product it bought', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $balm = polProduct($workspace, $user, 'RESPIRABALM');
    $patch = polProduct($workspace, $user, 'KIDNEY PATCH');

    polOrder($workspace, 'DN-1', [
        [polItem($workspace, 'balm-a', $balm), 1600],
        [polItem($workspace, 'patch-a', $patch), 2400],
    ]);

    $data = $this->actingAs($user)->getJson(polUrl($workspace))->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['label'])->toBe('DN-1')
        ->and((float) $data[0]['delivery_fee'])->toBe(1500.0)
        // Heaviest line first, so the biggest share reads at the top.
        ->and($data[0]['products'])->toBe([
            ['product' => 'KIDNEY PATCH', 'qty' => 2400, 'mapped' => true],
            ['product' => 'RESPIRABALM', 'qty' => 1600, 'mapped' => true],
        ]);
});

test('two SKUs of one product are one combined share, not a share each', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $balm = polProduct($workspace, $user, 'RESPIRABALM');

    polOrder($workspace, 'DN-1', [
        [polItem($workspace, 'balm-bfm', $balm), 1600],
        [polItem($workspace, 'balm-kintara', $balm), 3000],
    ]);

    $data = $this->actingAs($user)->getJson(polUrl($workspace))->assertOk()->json('data');

    expect($data[0]['products'])->toBe([
        ['product' => 'RESPIRABALM', 'qty' => 4600, 'mapped' => true],
    ]);
});

test('a SKU with no catalog product is still allocated, under its own name', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $balm = polProduct($workspace, $user, 'RESPIRABALM');

    polOrder($workspace, 'DN-1', [
        [polItem($workspace, 'balm-bfm', $balm), 2000],
        [polItem($workspace, 'Loose SKU (40)'), 2000],
    ]);

    $data = $this->actingAs($user)->getJson(polUrl($workspace))->assertOk()->json('data');

    // Leaving it out would hand its half of the fee to the other product.
    expect($data[0]['products'])->toContain(
        ['product' => 'Loose SKU (40)', 'qty' => 2000, 'mapped' => false],
    );
});

test('lines that bought nothing carry no weight in the split', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $balm = polProduct($workspace, $user, 'RESPIRABALM');
    $patch = polProduct($workspace, $user, 'KIDNEY PATCH');

    polOrder($workspace, 'DN-1', [
        [polItem($workspace, 'balm-a', $balm), 1600],
        [polItem($workspace, 'patch-a', $patch), 0],
    ]);

    $data = $this->actingAs($user)->getJson(polUrl($workspace))->assertOk()->json('data');

    expect($data[0]['products'])->toBe([
        ['product' => 'RESPIRABALM', 'qty' => 1600, 'mapped' => true],
    ]);
});

test('the search matches delivery, PO and control numbers, supplier and SKU', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    polOrder($workspace, 'DN-100', [[polItem($workspace, 'balm-a'), 10]], [
        'cust_po_no' => 'CPO-100',
        'control_no' => 'CN-100',
        'supplier' => 'Kintara',
    ]);
    polOrder($workspace, 'DN-200', [[polItem($workspace, 'patch-z'), 10]], [
        'supplier' => 'BFM',
    ]);

    $found = fn (string $term) => collect(
        $this->actingAs($user)->getJson(polUrl($workspace, ['search' => $term]))->assertOk()->json('data')
    )->pluck('label')->all();

    expect($found('DN-100'))->toBe(['DN-100'])
        ->and($found('CPO-100'))->toBe(['DN-100'])
        ->and($found('CN-100'))->toBe(['DN-100'])
        ->and($found('Kintara'))->toBe(['DN-100'])
        ->and($found('patch-z'))->toBe(['DN-200'])
        ->and($found('nothing-like-this'))->toBe([]);
});

test('orders of another workspace are never offered', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    polOrder($workspace, 'DN-MINE', [[polItem($workspace, 'a'), 10]]);
    polOrder($other, 'DN-THEIRS', [[polItem($other, 'b'), 10]]);

    $data = $this->actingAs($user)->getJson(polUrl($workspace))->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['label'])->toBe('DN-MINE');
});

test('a non-member cannot look up a workspace\'s orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['user' => $outsider] = makeWorkspaceWithOwner();

    polOrder($workspace, 'DN-1', [[polItem($workspace, 'a'), 10]]);

    $this->actingAs($outsider)->getJson(polUrl($workspace))->assertForbidden();
});

test('the delivery-fee-of-COGS types are the ones the form gets a picker for', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $make = fn (string $name) => TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'nature' => 'debit',
    ]);

    $freight = $make('Delivery Fee of COGS');
    $cogs = $make('Cost of Goods');
    $delivery = $make('Delivery Fee');
    $shipping = $make('COGS Shipping');

    $ids = $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/transactions/create")
        ->assertOk()
        ->viewData('page')['props']['cogsDeliveryTypeIds'];

    // Both halves have to be present: a plain COGS type and a general delivery
    // fee are ordinary entries with no order to split across.
    expect($ids)->toEqualCanonicalizing([$freight->id, $shipping->id])
        ->not->toContain($cogs->id)
        ->not->toContain($delivery->id);
});
