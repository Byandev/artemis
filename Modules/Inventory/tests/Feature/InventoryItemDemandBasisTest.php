<?php

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Products\Models\Product;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The Unit/Order toggle on the items list.
 *
 * The demand feed counts the same three days twice: as distinct orders, and as
 * the units those orders expand into once a bundle's composition is applied. The
 * toggle picks which of the two the demand columns report.
 *
 * Only demand converts — the 3-day average and Unfulfilled. Stock on hand,
 * incoming stock and the reorder plan built on them stay in units in both bases,
 * and that line is what most of these tests exist to hold. Stock is counted on a
 * shelf and bought in units, so a PO Needed computed from an order rate against
 * unit stock would buy a 3-units-per-order SKU at a third of what it consumes
 * and quietly run it out.
 */

/** An item with a product behind it, so the summary roll-up can reach it. */
function basisItem(Workspace $workspace, string $sku = 'WIDGET'): InventoryItem
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id]);
    Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);

    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'is_active' => true,
        'lead_time' => 10,
        'days_of_coverage' => 10,
    ]);
}

/**
 * A frozen row for the item.
 *
 * The defaults are one coherent scenario, and the arithmetic below is worked
 * against them: 18 units over 3 days across 6 orders is 6 units a day, 2 orders
 * a day and 3 units an order, with 30 units of stock left after fulfilment.
 */
function basisSnapshot(Workspace $workspace, InventoryItem $item, string $date, array $cols = []): void
{
    DB::table('inventory_item_snapshots')->insert(array_merge([
        'workspace_id' => $workspace->id,
        'inventory_item_id' => $item->id,
        'snapshot_date' => $date,
        'parent_id' => $item->parent_id,
        'is_parent' => (bool) $item->is_parent,
        'sku' => $item->sku,
        'is_active' => true,
        'lead_time' => 10,
        'days_of_coverage' => 10,
        // 18 units over three days is 6 a day, carried by 6 orders — 2 a day.
        'orders_3d' => 6,
        'units_3d' => 18,
        'unfulfilled_count' => 0,
        'unfulfilled_orders_count' => 0,
        // Stock is frozen once, in units. It has no order sibling.
        'current_stocks' => 30,
        'waiting_for_delivery_stocks' => 0,
        'remaining_after_fulfillment' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ], $cols));
}

/** The list's first row for the given query string. */
function basisRow($owner, Workspace $workspace, string $qs = ''): array
{
    $row = [];

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).$qs)
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$row) {
            $row = $page->toArray()['props']['items']['data'][0] ?? [];
        });

    return $row;
}

/** A partner workspace holding one snapshotted, bundled item. */
function basisWorkspace(array $cols = []): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    $item = basisItem($workspace);
    basisSnapshot($workspace, $item, now()->toDateString(), $cols);

    return ['owner' => $owner, 'workspace' => $workspace, 'item' => $item];
}

test('the unit basis reports units a day, and the order basis orders a day', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    // 18 units over three days.
    expect(basisRow($owner, $workspace)['three_days_average'])->toEqual(6);

    // The same three days, counted as the 6 orders that carried them.
    expect(basisRow($owner, $workspace, '?basis=order')['three_days_average'])->toEqual(2);
});

test('unfulfilled reports orders or units, and the stock beside it does not move', function () {
    // 12 units owed across 4 orders — both counted from the feed, neither
    // derived from the other. Stock is still counted and bought in units.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace([
        'unfulfilled_count' => 12,
        'unfulfilled_orders_count' => 4,
        'waiting_for_delivery_stocks' => 6,
    ]);

    $unit = basisRow($owner, $workspace);
    $order = basisRow($owner, $workspace, '?basis=order');

    expect($unit['unfulfilled_count'])->toEqual(12)
        ->and($order['unfulfilled_count'])->toEqual(4);

    foreach (['current_stocks', 'waiting_for_delivery_stocks'] as $column) {
        expect($order[$column])->toEqual($unit[$column]);
    }

    expect($order['current_stocks'])->toEqual(30)
        ->and($order['waiting_for_delivery_stocks'])->toEqual(6);
});

test('the reorder plan is priced in whichever basis is on', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    // lead 10 x 6 units/day = 60, buffer 10 x 6 = 60, less 30 units on hand.
    $unit = basisRow($owner, $workspace);

    expect($unit['stocks_needed_for_lead_time'])->toEqual(60)
        ->and($unit['po_qty'])->toEqual(60)
        ->and($unit['po_needed'])->toEqual(90)
        ->and($unit['remaining_after_fulfillment'])->toEqual(30);

    // The same figures at 2 orders a day and 3 units an order: 20 + 20 less the
    // 10 orders' worth of stock those 30 units represent.
    $order = basisRow($owner, $workspace, '?basis=order');

    expect($order['stocks_needed_for_lead_time'])->toEqual(20)
        ->and($order['po_qty'])->toEqual(20)
        ->and($order['po_needed'])->toEqual(30)
        ->and($order['remaining_after_fulfillment'])->toEqual(10);

    // The point of converting both sides: 30 orders at 3 units an order is the
    // 90 units the unit basis asked for. Same purchase, priced differently.
    expect($order['po_needed'] * 3)->toEqual($unit['po_needed']);
});

test('stock itself is not converted, only what is planned against it', function () {
    // Bought and counted on a shelf, so these read the same either way — which
    // is also what lets Stockout Risk keep dividing them by a unit rate.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace([
        'waiting_for_delivery_stocks' => 6,
    ]);

    $unit = basisRow($owner, $workspace);
    $order = basisRow($owner, $workspace, '?basis=order');

    foreach (['current_stocks', 'waiting_for_delivery_stocks'] as $column) {
        expect($order[$column])->toEqual($unit[$column]);
    }

    expect($order['current_stocks'])->toEqual(30)
        ->and($order['waiting_for_delivery_stocks'])->toEqual(6)
        // And the unit rate travels alongside for those cover cells.
        ->and($order['unit_three_days_average'])->toEqual(6);
});

test('the plan multiplies out from the rate the row displays', function () {
    // 17 units over three days is 5.667 a day, which the list shows as 6. The
    // buffer and the lead-time demand have to be 60, not the 56.67 the exact
    // rate would give — a reader multiplying what is on screen gets 60.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace(['units_3d' => 17]);

    $row = basisRow($owner, $workspace);

    expect($row['stocks_needed_for_lead_time'])->toEqual(60)
        ->and($row['po_qty'])->toEqual(60)
        // 60 + 60 less the 30 on hand.
        ->and($row['po_needed'])->toEqual(90);

    // Cover keeps the exact rate: it answers "how long will this last", which
    // is a measurement rather than a quantity to buy, and rounding the divisor
    // up would understate it.
    expect(round((float) $row['days_it_can_last'], 4))->toEqual(round(30 / (17 / 3), 4));
});

test('the rounding follows the basis, not the units behind it', function () {
    // 7 orders over three days is 2.333 a day, displayed as 3 — so the order
    // plan works from 3, not from the unit rate's ceiling.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace(['orders_3d' => 7]);

    $row = basisRow($owner, $workspace, '?basis=order');

    expect($row['stocks_needed_for_lead_time'])->toEqual(30)
        ->and($row['po_qty'])->toEqual(30);
});

test('days of cover is the same in both bases', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    // 30 units at 6 a day, and the 10 orders those are at 2 a day: both five
    // days. Cover is the one figure the conversion cancels out of, so a toggle
    // that moved it would mean one side had not converted.
    expect(basisRow($owner, $workspace)['days_it_can_last'])->toEqual(5)
        ->and(basisRow($owner, $workspace, '?basis=order')['days_it_can_last'])->toEqual(5);
});

test('a group that took no orders reports no order figures', function () {
    // No orders over the window means no observed units-per-order, so the
    // snapshotter had nothing to divide by and froze nulls. The list says "—"
    // rather than inventing a rate: a 0 would read as "it sold nothing".
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace([
        'orders_3d' => null,
        'unfulfilled_orders_count' => null,
    ]);

    $row = basisRow($owner, $workspace, '?basis=order');

    expect($row['three_days_average'])->toBeNull()
        ->and($row['unfulfilled_count'])->toBeNull()
        // The plan is priced in orders now, so it has nothing to say either.
        ->and($row['remaining_after_fulfillment'])->toBeNull()
        ->and($row['stocks_needed_for_lead_time'])->toBeNull();

    // Cover still answers: it is measured in units, which the day did record.
    expect($row['days_it_can_last'])->toEqual(5);

    // And the unit basis still answers for the same day, because it was measured.
    expect(basisRow($owner, $workspace)['three_days_average'])->toEqual(6);
});

test('the order rate is exactly what the Orders/d 3d column reports', function () {
    // The bug this replaced: deriving the rate by dividing each item's unit
    // average through a rounded units-per-order landed on 19.0008 where the
    // truth was 19, and the ceiling the list applies made that a whole extra
    // order. Reading orders_3d directly is the same expression the report
    // column renders, so the two cannot drift.
    //
    // 19 orders a day over three days, split across two SKUs whose unit
    // averages do not divide evenly — the shape that used to drift.
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    $parent = basisItem($workspace, 'PARENT');
    $parent->update(['is_parent' => true]);

    foreach (['CHILD-A' => 21, 'CHILD-B' => 12] as $sku => $units) {
        $child = basisItem($workspace, $sku);
        $child->update(['parent_id' => $parent->id]);

        basisSnapshot($workspace, $child, now()->toDateString(), [
            'parent_id' => $parent->id,
            'units_3d' => $units,
            'orders_3d' => 57,
        ]);
    }

    basisSnapshot($workspace, $parent, now()->toDateString(), [
        'is_parent' => true,
        'units_3d' => 0,
        'orders_3d' => 57,
    ]);

    // 57 orders over three days is exactly 19 a day, and the column says 19.
    expect(basisRow($owner, $workspace, '?basis=order')['three_days_average'])
        ->toEqual(19);
});

test('the unit rate is sent alongside whatever basis is displayed', function () {
    // Stockout risk and Stocks Last divide unit stock by a rate, so the page
    // needs units a day even when the 3-Day Avg column has become orders.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    $order = basisRow($owner, $workspace, '?basis=order');

    expect($order['three_days_average'])->toEqual(2)
        ->and($order['unit_three_days_average'])->toEqual(6);

    expect(basisRow($owner, $workspace)['unit_three_days_average'])->toEqual(6);
});

test('the flat per-SKU list ignores the order basis', function () {
    // orders_3d is the group's figure stamped on every row of the group, so on a
    // flat list each sibling would repeat the group's order count as its own.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    $row = basisRow($owner, $workspace, '?summarize=0&basis=order');

    expect($row['three_days_average'])->toEqual(6);
});

test('a workspace reading live figures ignores the order basis', function () {
    // No snapshot means no frozen order counts to switch to.
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    basisItem($workspace);

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?basis=order')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('basis', 'unit')
            ->where('basisAvailable', false));
});

test('the snapshot freezes the counts both bases are read from', function () {
    // End to end: the command writes the order and unit counts, and the toggle
    // reads its rate off them — no second denomination is stored.
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);

    $item = basisItem($workspace);

    InventoryTransaction::create([
        'workspace_id' => $workspace->id,
        'inventory_item_id' => $item->id,
        'date' => now()->toDateString(),
        'ref_no' => 'TXN-BASIS',
        'remaining_qty' => 30,
    ]);

    // One bundle carries three of the item, so each order is three units.
    DB::table('inventory_unit_codes')->insert([
        'workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-A', 'sku' => 'BUNDLE-A',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('inventory_unit_code_items')->insert([
        'workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-A',
        'item_code' => $item->sku, 'quantity' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Six orders across the three-day window: 18 units, 3 per order.
    foreach (range(1, 6) as $n) {
        DB::table('gencys_orders')->insert([
            'id' => $n, 'workspace_id' => $workspace->id, 'order_no' => 'GO-'.$n,
            'order_date' => now()->subDays($n % 3)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gencys_order_items')->insert([
            'order_id' => $n, 'sku' => 'BUNDLE-A', 'quantity' => 99,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    test()->artisan('inventory:snapshot-items', ['--ignore-sync' => true])->assertSuccessful();

    $row = DB::table('inventory_item_snapshots')
        ->where('inventory_item_id', $item->id)
        ->first();

    // Both counts are frozen, and the order rate is read off them at query
    // time — 6 orders over three days is the 2 a day the list shows.
    // One count, recorded per item: 6 orders carrying 18 units over three days.
    expect((int) $row->orders_3d)->toBe(6)
        ->and((int) $row->units_3d)->toBe(18)
        ->and((float) $row->current_stocks)->toBe(30.0);
});

test('the snapshot counts unfulfilled orders rather than inferring them', function () {
    // Two open orders carrying three units each. Converting 6 units through the
    // 3 units-per-order the window averaged would land on 2 as well — so the
    // fixture makes them disagree: one open order carries a single unit.
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);

    $item = basisItem($workspace);

    DB::table('inventory_unit_codes')->insert([
        ['workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-3', 'sku' => 'BUNDLE-3', 'created_at' => now(), 'updated_at' => now()],
        ['workspace_id' => $workspace->id, 'unit_code' => 'SINGLE', 'sku' => 'SINGLE', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('inventory_unit_code_items')->insert([
        ['workspace_id' => $workspace->id, 'unit_code' => 'BUNDLE-3', 'item_code' => $item->sku, 'quantity' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['workspace_id' => $workspace->id, 'unit_code' => 'SINGLE', 'item_code' => $item->sku, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Three open orders: two bundles of three and one single. 7 units, 3 orders.
    foreach ([['BUNDLE-3', 1], ['BUNDLE-3', 2], ['SINGLE', 3]] as [$code, $n]) {
        DB::table('gencys_orders')->insert([
            'id' => $n, 'workspace_id' => $workspace->id, 'order_no' => 'GO-'.$n,
            'order_date' => now()->toDateString(), 'parcel_status' => 'ENCODED',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gencys_order_items')->insert([
            'order_id' => $n, 'sku' => $code, 'quantity' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    test()->artisan('inventory:snapshot-items', ['--ignore-sync' => true])->assertSuccessful();

    $row = DB::table('inventory_item_snapshots')->where('inventory_item_id', $item->id)->first();

    expect((int) $row->unfulfilled_count)->toBe(7)
        ->and((int) $row->unfulfilled_orders_count)->toBe(3);
});

test('the page says whether the order basis is on offer', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?basis=order')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('basis', 'order')
            ->where('basisAvailable', true));

    // Offered, but not on, until it is asked for.
    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('basis', 'unit')
            ->where('basisAvailable', true));
});
