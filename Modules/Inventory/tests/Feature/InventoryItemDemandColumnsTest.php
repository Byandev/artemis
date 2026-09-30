<?php

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Modules\Products\Models\Product;
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

test('the list filters to one bottleneck stage', function () {
    // Frozen with the day, so the filter reads the stored label rather than
    // re-deriving a classification the page would then have to agree with.
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    foreach (['Warehouse' => 'WH-SKU', 'Late PO' => 'PO-SKU'] as $stage => $sku) {
        $item = columnsItem($workspace, $sku);
        columnsSnapshot($workspace, $item, ['units_3d' => 30, 'bottleneck_stage' => $stage]);
    }

    // Unfiltered, both are listed.
    expect(demandRows($owner, $workspace))->toHaveKeys(['WH-SKU', 'PO-SKU']);

    $filtered = demandRows($owner, $workspace, '?filter[bottleneck_stage]=Warehouse');

    expect($filtered)->toHaveKey('WH-SKU')
        ->and($filtered)->not->toHaveKey('PO-SKU');
});

test('the page offers the classifier own stages, not its own copy', function () {
    ['owner' => $owner, 'workspace' => $workspace] = demandGroup();

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'bottleneckStages',
            ['Warehouse', 'Delayed Stocks', 'Scaling Item', 'Late PO'],
        ));
});

test('the flat list filters on the stage too', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    foreach (['Warehouse' => 'WH-SKU', 'Late PO' => 'PO-SKU'] as $stage => $sku) {
        $item = columnsItem($workspace, $sku);
        columnsSnapshot($workspace, $item, ['units_3d' => 30, 'bottleneck_stage' => $stage]);
    }

    $filtered = demandRows($owner, $workspace, '?summarize=0&filter[bottleneck_stage]=Late PO');

    expect($filtered)->toHaveKey('PO-SKU')
        ->and($filtered)->not->toHaveKey('WH-SKU');
});

/**
 * Every edit that changes what the frozen row says has to rewrite it.
 *
 * The list reads the snapshot first — COALESCE(snap.lead_time, item.lead_time)
 * — so an edit that only touched inventory_items appeared to do nothing at all.
 * That is how the item dialog's lead time went unnoticed: the inline editor on
 * the list refreshed and the dialog did not, and only one of them had a test.
 */
function editWorkspace(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeGencysWorkspaceWithOwner();

    $item = columnsItem($workspace, 'EDIT-SKU');
    // A real feed, because a refresh recomputes demand from it — 10 a day over
    // the window, so lead 10 gives 100 needed.
    seedDemandFeed($item, 10);
    columnsSnapshot($workspace, $item, ['units_3d' => 30]);

    return ['owner' => $owner, 'workspace' => $workspace, 'item' => $item];
}

test('the item dialog rewrites the frozen row it just contradicted', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'item' => $item] = editWorkspace();

    expect(demandRows($owner, $workspace)['EDIT-SKU']['stocks_needed_for_lead_time'])
        ->toEqual(100);

    test()->actingAs($owner)
        ->put(route('workspaces.inventory.item.update', ['workspace' => $workspace, 'item' => $item->id]), [
            'sku' => 'EDIT-SKU',
            'is_active' => true,
            'lead_time' => 20,
        ])
        ->assertRedirect();

    expect((int) InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->where('snapshot_date', now()->toDateString())->value('lead_time'))->toBe(20);

    // And the list agrees at once: 20 days at 10 a day.
    expect(demandRows($owner, $workspace)['EDIT-SKU']['stocks_needed_for_lead_time'])
        ->toEqual(200);
});

test('the inline lead-time editor still rewrites it', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'item' => $item] = editWorkspace();

    test()->actingAs($owner)
        ->patch(route('workspaces.inventory.item.lead-time.update', ['workspace' => $workspace, 'item' => $item->id]), ['lead_time' => 20])
        ->assertRedirect();

    expect(demandRows($owner, $workspace)['EDIT-SKU']['stocks_needed_for_lead_time'])
        ->toEqual(200);
});

test('deactivating in bulk rewrites the frozen rows', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'item' => $item] = editWorkspace();

    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-status', $workspace), [
            'ids' => [$item->id], 'is_active' => false,
        ])
        ->assertRedirect();

    expect((bool) InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->where('snapshot_date', now()->toDateString())->value('is_active'))->toBeFalse();
});

test('regrouping rewrites the group left behind as well as the one joined', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'item' => $item] = editWorkspace();

    $parent = columnsItem($workspace, 'NEW-PARENT');
    $parent->update(['is_parent' => true]);
    columnsSnapshot($workspace, $parent, ['is_parent' => true, 'units_3d' => 0]);

    test()->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$item->id], 'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    // The frozen row carries the parent it now belongs to, so the roll-up puts
    // it under that group rather than listing it on its own.
    expect((int) InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->where('snapshot_date', now()->toDateString())->value('parent_id'))->toBe($parent->id);

    $rows = demandRows($owner, $workspace);

    expect($rows)->toHaveKey('NEW-PARENT')
        ->and($rows)->not->toHaveKey('EDIT-SKU');
});
