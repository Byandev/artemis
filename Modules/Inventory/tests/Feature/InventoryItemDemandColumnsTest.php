<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * "3-Day Avg" and the report's "Units/d 3d" are the same measurement — units a
 * day over the same three days of the same order feed — so the page must never
 * show two different numbers for them.
 *
 * It used to, because the two were counted separately: GencysDemandSync wrote a
 * per-SKU three_days_average while ItemReportFacts wrote a per-group units_3d.
 * Divide the group total by three and you got the group's rate against one
 * SKU's average on the flat list, and the whole group's rate against a narrowed
 * average whenever a filter hid part of a group. There is one count now,
 * recorded per item, and both columns read it.
 */

/** An item with a product behind it, so the summary roll-up can reach it. */
function columnsItem(Workspace $workspace, string $sku): InventoryItem
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

/** A frozen row carrying this item's own demand. */
function columnsSnapshot(Workspace $workspace, InventoryItem $item, array $cols = []): void
{
    DB::table('inventory_item_snapshots')->insert(array_merge([
        'workspace_id' => $workspace->id,
        'inventory_item_id' => $item->id,
        'snapshot_date' => now()->toDateString(),
        'parent_id' => $item->parent_id,
        'is_parent' => (bool) $item->is_parent,
        'sku' => $item->sku,
        'is_active' => true,
        'lead_time' => 10,
        'days_of_coverage' => 10,
        'current_stocks' => 100,
        'remaining_after_fulfillment' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ], $cols));
}

/**
 * A parent with two children, each carrying its own share of the demand:
 * 21 and 12 units over three days, so 7 and 4 a day, 11 for the group.
 */
function demandGroup(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    $parent = columnsItem($workspace, 'PARENT-SKU');
    $parent->update(['is_parent' => true]);

    // A parent carries no demand of its own; the group's is its children's.
    // The wider windows are the group's figure, stamped on every row of it.
    $groupWindows = ['orders_3d' => 30, 'units_7d' => 70, 'orders_7d' => 64, 'units_14d' => 140, 'orders_14d' => 128];

    columnsSnapshot($workspace, $parent, ['is_parent' => true, 'units_3d' => 0] + $groupWindows);

    foreach (['CHILD-A' => 21, 'CHILD-B' => 12] as $sku => $units) {
        $child = columnsItem($workspace, $sku);
        $child->update(['parent_id' => $parent->id]);

        columnsSnapshot($workspace, $child, [
            'parent_id' => $parent->id,
            'units_3d' => $units,
        ] + $groupWindows);
    }

    return ['owner' => $owner, 'workspace' => $workspace];
}

/** Every row the list renders, keyed by SKU. */
function demandRows($owner, $workspace, string $qs = ''): array
{
    $rows = [];

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).$qs)
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows) {
            $rows = collect($page->toArray()['props']['items']['data'])->keyBy('sku')->all();
        });

    return $rows;
}

test('the rolled-up group reports one rate for both columns', function () {
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    $row = demandRows($owner, $workspace)['PARENT-SKU'];

    // 7 + 4 a day, from the 21 + 12 units the group's rows carry.
    expect($row['three_days_average'])->toEqual(11)
        ->and($row['units_3d'])->toEqual(33);
});

test('a filter that hides part of a group narrows both, not one', function () {
    // The group figure used to stay whole whatever was on screen, so the rate
    // beside a narrowed average reported demand the rows did not account for.
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    $row = demandRows($owner, $workspace, '?filter[search]=CHILD-A')['PARENT-SKU'];

    expect($row['three_days_average'])->toEqual(7)
        ->and($row['units_3d'])->toEqual(21);
});

test('the flat list reports each SKU own rate', function () {
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    $rows = demandRows($owner, $workspace, '?summarize=0');

    expect($rows['CHILD-A']['three_days_average'])->toEqual(7)
        ->and($rows['CHILD-B']['three_days_average'])->toEqual(4)
        // And the demand behind each rate is that SKU's own, not the group's.
        ->and($rows['CHILD-A']['units_3d'])->toEqual(21)
        ->and($rows['CHILD-B']['units_3d'])->toEqual(12);
});

test('both denominations of every window reach the page', function () {
    // The table shows one column per window and picks the denomination from the
    // toggle, so the payload has to carry both for all three windows — the
    // switch is a render, not another request shape.
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    foreach (['', '?basis=order'] as $qs) {
        $row = demandRows($owner, $workspace, $qs)['PARENT-SKU'];

        foreach (['units_3d', 'units_7d', 'units_14d', 'orders_3d', 'orders_7d', 'orders_14d'] as $column) {
            expect($row)->toHaveKey($column);
        }
    }
});

test('the wider windows stay whole while the three-day one follows the rows', function () {
    // units_3d is per item and sums to what is on screen; the wider windows sit
    // beside the distinct-order counts and stay the group's, which is why the
    // table blanks them on the flat list rather than repeating them per SKU.
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    $row = demandRows($owner, $workspace, '?filter[search]=CHILD-A')['PARENT-SKU'];

    expect($row['units_3d'])->toEqual(21)
        ->and($row['orders_3d'])->toEqual(30);
});

test('the reorder figures follow the item own demand', function () {
    // These used to be frozen from inventory_items.three_days_average, which a
    // Gencys partner no longer fills — they are derived from the same demand
    // the rate is, so a row's figures and its rate cannot disagree.
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    $row = demandRows($owner, $workspace, '?summarize=0')['CHILD-A'];

    // 7 a day: 10 days of lead and 10 of cover is 70 + 70, less 100 on hand.
    expect($row['stocks_needed_for_lead_time'])->toEqual(70)
        ->and($row['po_qty'])->toEqual(70)
        ->and($row['po_needed'])->toEqual(40)
        // Compared at four places: MySQL returns the division at its own scale.
        ->and(round((float) $row['days_it_can_last'], 4))->toEqual(round(100 / 7, 4));
});
