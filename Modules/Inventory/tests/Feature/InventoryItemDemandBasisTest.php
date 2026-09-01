<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
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
        'three_days_average' => 6,
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
        'three_days_average' => 6,
        'orders_3d' => 6,
        'units_3d' => 18,
        // The demand figures the snapshotter freezes in orders beside the unit
        // ones, each divided by the group's 3 units per order.
        'units_per_order' => 3,
        'three_days_average_orders' => 2,
        'unfulfilled_count' => 0,
        'unfulfilled_count_orders' => 0,
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

test('unfulfilled converts, and the stock beside it does not', function () {
    // 12 units owed across 4 orders, against stock that is still counted and
    // bought in units.
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace([
        'unfulfilled_count' => 12,
        'unfulfilled_count_orders' => 4,
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

test('the reorder plan stays in units whichever basis is on', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    // lead 10 x 6 units/day = 60, buffer 10 x 6 = 60, less 30 units on hand.
    $unit = basisRow($owner, $workspace);
    $order = basisRow($owner, $workspace, '?basis=order');

    // Not one of these moves. Planning 2 orders a day against 30 units of stock
    // would order this SKU at a third of what it actually consumes.
    foreach ([
        'stocks_needed_for_lead_time',
        'po_qty',
        'po_needed',
        'days_it_can_last',
        'remaining_after_fulfillment',
    ] as $column) {
        expect($order[$column])->toEqual($unit[$column]);
    }

    expect($unit['stocks_needed_for_lead_time'])->toEqual(60)
        ->and($unit['po_qty'])->toEqual(60)
        ->and($unit['po_needed'])->toEqual(90)
        ->and($unit['remaining_after_fulfillment'])->toEqual(30);
});

test('days of cover is the same in both bases', function () {
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace();

    // 30 units at 6 units a day. Cover is a unit figure over a unit rate, and
    // the toggle reaches neither.
    expect(basisRow($owner, $workspace)['days_it_can_last'])->toEqual(5)
        ->and(basisRow($owner, $workspace, '?basis=order')['days_it_can_last'])->toEqual(5);
});

test('a group that took no orders reports no order figures', function () {
    // No orders over the window means no observed units-per-order, so the
    // snapshotter had nothing to divide by and froze nulls. The list says "—"
    // rather than inventing a rate: a 0 would read as "it sold nothing".
    ['owner' => $owner, 'workspace' => $workspace] = basisWorkspace([
        'orders_3d' => null,
        'units_3d' => null,
        'units_per_order' => null,
        'three_days_average_orders' => null,
        'unfulfilled_count_orders' => null,
    ]);

    $row = basisRow($owner, $workspace, '?basis=order');

    expect($row['three_days_average'])->toBeNull()
        ->and($row['unfulfilled_count'])->toBeNull();

    // The plan is untouched, because it never read the order figures.
    expect($row['po_needed'])->toEqual(90)
        ->and($row['days_it_can_last'])->toEqual(5);

    // And the unit basis still answers for the same day, because it was measured.
    expect(basisRow($owner, $workspace)['three_days_average'])->toEqual(6);
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

test('the snapshot freezes the order figures beside the unit ones', function () {
    // End to end: the command writes both denominations, so the toggle reads
    // stored columns rather than re-deriving a second calculation at page load.
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

    expect((int) $row->orders_3d)->toBe(6)
        ->and((int) $row->units_3d)->toBe(18)
        ->and((float) $row->units_per_order)->toBe(3.0)
        // 18 units over three days is 6 units a day, which is 2 orders a day.
        ->and((float) $row->three_days_average)->toBe(6.0)
        ->and((float) $row->three_days_average_orders)->toBe(2.0);

    // Stock is frozen once, in units, and carries no order sibling.
    expect((float) $row->current_stocks)->toBe(30.0)
        ->and($row->unfulfilled_count_orders)->not->toBeNull();
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
