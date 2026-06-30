<?php

use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;

/** A unit code "BUNDLE-A" = 2× SKU-1 + 1× SKU-2 for the given workspace. */
function seedBundleA($workspace): void
{
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'BUNDLE-A',
        'sku' => 'BD-A',
        'total_amount' => 100,
    ]);

    InventoryUnitCodeItem::insert([
        ['workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-A', 'item_code' => 'SKU-1', 'quantity' => 2],
        ['workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-A', 'item_code' => 'SKU-2', 'quantity' => 1],
    ]);

    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-2', 'is_active' => true]);
}

/** Create a Gencys order with one line for the given unit-code sku. */
function gencysOrder(int $id, $workspace, string $unitCodeSku, $orderDate, string $orderStatus): void
{
    GencysDailySalesOrder::create([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'order_date' => $orderDate,
        'order_status' => $orderStatus,
    ]);

    // quantity here is deliberately large to prove the command ignores it.
    GencysDailySalesOrderItem::create([
        'order_id' => $id,
        'sku' => $unitCodeSku,
        'quantity' => 99,
    ]);
}

test('it expands unit codes from gencys orders into per-item demand, ignoring the line quantity', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    seedBundleA($workspace);

    // Two in-window orders: one open (unfulfilled), one fulfilled.
    gencysOrder(1001, $workspace, 'BUNDLE-A', now()->subDay(), 'New');
    gencysOrder(1002, $workspace, 'BUNDLE-A', now()->subDay(), 'Delivered');
    // One open order outside the 3-day window (counts for unfulfilled only).
    gencysOrder(1003, $workspace, 'BUNDLE-A', now()->subDays(10), 'ENCODED');

    $this->artisan('gencys-erp:sync-inventory-from-orders')->assertSuccessful();

    $sku1 = InventoryItem::where('workspace_id', $workspace->id)->where('sku', 'SKU-1')->first();
    $sku2 = InventoryItem::where('workspace_id', $workspace->id)->where('sku', 'SKU-2')->first();

    // 3-day average: 2 in-window order lines × component qty ÷ 3.
    //   SKU-1: 2 lines × 2 = 4 → 4/3 = 1.3333 ; SKU-2: 2 × 1 = 2 → 0.6667
    expect((float) $sku1->three_days_average)->toBe(1.3333)
        ->and((float) $sku2->three_days_average)->toBe(0.6667)
        // Unfulfilled (open status): orders 1001 (New) + 1003 (ENCODED) = 2 lines.
        //   SKU-1: 2 × 2 = 4 ; SKU-2: 2 × 1 = 2
        ->and($sku1->unfulfilled_count)->toBe(4)
        ->and($sku2->unfulfilled_count)->toBe(2);
});

test('it skips workspaces that are not Gencys partners', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => false]);

    seedBundleA($workspace);
    gencysOrder(2001, $workspace, 'BUNDLE-A', now()->subDay(), 'New');

    // Sentinel values that should survive untouched if the workspace is skipped.
    InventoryItem::where('workspace_id', $workspace->id)
        ->update(['three_days_average' => 7.5, 'unfulfilled_count' => 42]);

    $this->artisan('gencys-erp:sync-inventory-from-orders')->assertSuccessful();

    $sku1 = InventoryItem::where('workspace_id', $workspace->id)->where('sku', 'SKU-1')->first();
    expect((float) $sku1->three_days_average)->toBe(7.5)
        ->and($sku1->unfulfilled_count)->toBe(42);
});
