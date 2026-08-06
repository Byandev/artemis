<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** The dashboard's delivery lead-time payload for a workspace. */
function leadTime($user, $workspace, array $params = []): array
{
    $query = $params ? '?'.http_build_query($params) : '';

    return test()->actingAs($user)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/delivery-lead-time{$query}")
        ->assertOk()
        ->json();
}

/**
 * A PO line for $item ordering $count units, issued on $issuedAt, with
 * $deliveries as [date => qty] pairs.
 */
function poLine(InventoryItem $item, string $issuedAt, int $count, array $deliveries = [], int $status = 6): PurchasedOrderItem
{
    $order = PurchasedOrder::create([
        'workspace_id' => $item->workspace_id,
        'control_no' => 'PO-'.uniqid(),
        'issue_date' => $issuedAt,
        'status' => $status,
    ]);

    $line = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => $count,
    ]);

    foreach ($deliveries as $date => $qty) {
        $line->deliveries()->create(['delivery_date' => $date, 'qty' => $qty]);
    }

    return $line;
}

test('each fill level is stamped with the delivery that crossed it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    // 100 ordered on Jul 1: 30 on the 11th (30%), 40 on the 21st (70%),
    // 30 on Aug 5 (100%).
    poLine($item, '2026-07-01', 100, [
        '2026-07-11' => 30,
        '2026-07-21' => 40,
        '2026-08-05' => 30,
    ]);

    $data = leadTime($owner, $workspace);
    $row = $data['items'][0];

    expect($row['averages']['25'])->toEqual(10.0)
        ->and($row['averages']['50'])->toEqual(20.0)
        // 75% is only crossed by the final delivery, same date as 100%.
        ->and($row['averages']['75'])->toEqual(35.0)
        ->and($row['averages']['100'])->toEqual(35.0)
        ->and($row['lines'])->toBe(1);
});

test('a partially delivered line counts only toward the levels it has reached', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    // 60 of 100 delivered — 25% and 50% crossed, 75% and 100% not.
    poLine($item, '2026-07-01', 100, ['2026-07-09' => 60]);

    $data = leadTime($owner, $workspace);
    $row = $data['items'][0];

    expect($row['averages']['25'])->toEqual(8.0)
        ->and($row['averages']['50'])->toEqual(8.0)
        // Unreached is unknown, not zero — a null keeps it out of the average.
        ->and($row['averages']['75'])->toBeNull()
        ->and($row['averages']['100'])->toBeNull()
        ->and($row['samples']['50'])->toBe(1)
        ->and($row['samples']['100'])->toBe(0);
});

test('averages span every line for the item', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    poLine($item, '2026-07-01', 10, ['2026-07-11' => 10]);   // 10 days
    poLine($item, '2026-07-01', 10, ['2026-07-21' => 10]);   // 20 days

    $data = leadTime($owner, $workspace);

    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['averages']['100'])->toEqual(15.0)
        ->and($data['items'][0]['lines'])->toBe(2);
});

test('only orders issued in the last six months are counted', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $recent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'RECENT',
        'is_active' => true,
    ]);
    $stale = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'STALE',
        'is_active' => true,
    ]);

    $inWindow = now()->subMonths(2);
    $outOfWindow = now()->subMonths(9);

    poLine($recent, $inWindow->toDateString(), 10, [$inWindow->copy()->addDays(5)->toDateString() => 10]);
    poLine($stale, $outOfWindow->toDateString(), 10, [$outOfWindow->copy()->addDays(5)->toDateString() => 10]);

    $data = leadTime($owner, $workspace);

    expect(array_column($data['items'], 'sku'))->toBe(['RECENT']);
});

test('cancelled orders are excluded rather than counted as never delivered', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    poLine($item, '2026-07-01', 10, [], PurchasedOrder::CANCELLED);

    $data = leadTime($owner, $workspace);

    expect($data['items'])->toBeEmpty()
        ->and($data['overall']['lines'])->toBe(0);
});

test('children roll into their parent when grouping, and split when not', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'PARENT',
        'is_parent' => true,
        'is_active' => true,
    ]);
    $childA = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'CHILD-A',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
    $childB = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'CHILD-B',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    poLine($childA, '2026-07-01', 10, ['2026-07-11' => 10]);   // 10 days
    poLine($childB, '2026-07-01', 10, ['2026-07-31' => 10]);   // 30 days

    $grouped = leadTime($owner, $workspace, ['group_by_parent' => 1]);

    expect($grouped['items'])->toHaveCount(1);
    expect($grouped['items'][0]['sku'])->toBe('PARENT')
        ->and($grouped['items'][0]['is_group'])->toBeTrue()
        ->and($grouped['items'][0]['child_count'])->toBe(2)
        ->and($grouped['items'][0]['lines'])->toBe(2)
        ->and($grouped['items'][0]['averages']['100'])->toEqual(20.0);

    $flat = leadTime($owner, $workspace, ['group_by_parent' => 0]);

    expect(array_column($flat['items'], 'sku'))->toBe(['CHILD-A', 'CHILD-B']);
    expect($flat['items'][0]['averages']['100'])->toEqual(10.0)
        ->and($flat['items'][1]['averages']['100'])->toEqual(30.0)
        ->and($flat['items'][0]['is_group'])->toBeFalse();
});

test('rows are listed by product name, unnamed items last', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $products = [];

    // Created out of order, so the sort cannot be passing by accident. The
    // lowercase entry would sort after every capitalised one byte-wise.
    foreach (['Zinc Tablets', 'apple Cider', 'Multivitamin'] as $name) {
        $products[$name] = Product::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
        ]);
    }

    foreach ($products as $name => $product) {
        $item = InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => 'SKU-'.$product->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        poLine($item, '2026-07-01', 10, ['2026-07-11' => 10]);
    }

    // No product attached — it has no name to sort by.
    $unnamed = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-UNNAMED',
        'is_active' => true,
    ]);
    poLine($unnamed, '2026-07-01', 10, ['2026-07-11' => 10]);

    $data = leadTime($owner, $workspace);

    expect(array_column($data['items'], 'product_name'))
        // Case-insensitive, so "apple Cider" leads rather than trailing.
        ->toBe(['apple Cider', 'Multivitamin', 'Zinc Tablets', null]);
});

test('the overall summary weighs every line equally, not every row', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $busy = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'BUSY',
        'is_active' => true,
    ]);
    $quiet = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'QUIET',
        'is_active' => true,
    ]);

    // Three fast lines against one slow one: a row-level mean would say 20,
    // a line-level mean says 15.
    poLine($busy, '2026-07-01', 10, ['2026-07-11' => 10]);
    poLine($busy, '2026-07-01', 10, ['2026-07-11' => 10]);
    poLine($busy, '2026-07-01', 10, ['2026-07-11' => 10]);
    poLine($quiet, '2026-07-01', 10, ['2026-07-31' => 10]);

    $data = leadTime($owner, $workspace);

    expect($data['overall']['lines'])->toBe(4)
        ->and($data['overall']['averages']['100'])->toEqual(15.0)
        ->and($data['overall']['samples']['100'])->toBe(4);
});

test('a delivery dated before its order floors at same-day rather than going negative', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    poLine($item, '2026-07-10', 10, ['2026-07-01' => 10]);

    $data = leadTime($owner, $workspace);

    expect($data['items'][0]['averages']['100'])->toEqual(0.0);
});
