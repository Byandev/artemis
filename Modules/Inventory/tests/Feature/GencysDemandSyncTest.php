<?php

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Modules\Inventory\Support\GencysDemandSync;
use Modules\Inventory\Support\InventoryItemSnapshotter;
use Modules\Inventory\Support\ItemReportFacts;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A workspace wired for Gencys: the partner flag plus credentials on file. */
function gencysWorkspace(array $overrides = []): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(array_merge([
        'is_gencys_partner' => true,
        'erp_username' => 'erp-user',
        'erp_password' => 'erp-pass',
    ], $overrides))->save();

    return $workspace;
}

/** A unit code expanding into SKU => qty-per-bundle. */
function demandUnitCode(Workspace $workspace, string $code, array $components): void
{
    DB::table('inventory_unit_codes')->insert([
        'workspace_id' => $workspace->id, 'unit_code' => $code, 'sku' => $code,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ($components as $itemCode => $qty) {
        DB::table('inventory_unit_code_items')->insert([
            'workspace_id' => $workspace->id, 'unit_code' => $code,
            'item_code' => $itemCode, 'quantity' => $qty,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** A Gencys order line naming $code, dated $date, at the given parcel status. */
function demandOrder(Workspace $workspace, string $code, string $date, ?string $parcelStatus = null): void
{
    static $nextId = 1;
    $id = $nextId++;

    DB::table('gencys_orders')->insert([
        'id' => $id, 'workspace_id' => $workspace->id, 'order_no' => 'GO-'.$id,
        'order_date' => $date, 'parcel_status' => $parcelStatus,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('gencys_order_items')->insert([
        'order_id' => $id, 'sku' => $code, 'quantity' => 99,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('only Gencys partners with ERP credentials are eligible', function () {
    $wired = gencysWorkspace();
    $noCredentials = gencysWorkspace(['erp_username' => null, 'erp_password' => null]);
    $blankCredentials = gencysWorkspace(['erp_username' => '', 'erp_password' => '']);
    $notPartner = gencysWorkspace(['is_gencys_partner' => false]);

    $eligible = GencysDemandSync::eligibleWorkspaces()->pluck('id')->all();

    expect($eligible)->toContain($wired->id)
        // The flag is intent; the credentials are evidence the feed is wired up.
        ->and($eligible)->not->toContain($noCredentials->id)
        ->and($eligible)->not->toContain($blankCredentials->id)
        ->and($eligible)->not->toContain($notPartner->id);
});

test('the sync expands unit codes into per-item demand', function () {
    $workspace = gencysWorkspace();

    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GADGET', 'is_active' => true]);

    // One bundle is 3 widgets and 1 gadget; the line quantity of 99 is ignored.
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 3, 'GADGET' => 1]);

    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());
    demandOrder($workspace, 'BUNDLE-A', now()->subDay()->toDateString());
    // Outside the 3-day window.
    demandOrder($workspace, 'BUNDLE-A', now()->subDays(9)->toDateString());
    // Open order, so it counts as unfulfilled whatever its date.
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString(), 'ENCODED');

    (new GencysDemandSync($workspace))->run();

    // One order is open: 3 widgets, 1 gadget. The sales rate is not this class's
    // to write any more — ItemReportFacts counts the same orders once, into
    // units_3d, and the assertion below reads it there.
    expect($a->fresh()->unfulfilled_count)->toBe(3)
        ->and($b->fresh()->unfulfilled_count)->toBe(1);

    // 3 in-window orders x 3 per bundle = 9 widgets over 3 days, per item.
    $facts = new ItemReportFacts($workspace);

    expect($facts->itemFacts($a->id)['units_3d'])->toBe(9)
        ->and($facts->itemFacts($b->id)['units_3d'])->toBe(3);
});

test('demand is measured from the feed, not from today', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 6]);

    // The whole feed stopped a fortnight ago. Anchored to today this would find
    // nothing and write a zero average, which the list reads as "not selling" —
    // and turns into infinite cover and a PO Needed of nothing.
    demandOrder($workspace, 'BUNDLE-A', now()->subDays(14)->toDateString());
    demandOrder($workspace, 'BUNDLE-A', now()->subDays(15)->toDateString());

    // 2 orders x 6 per bundle = 12 units in the window, so 4 a day.
    expect((new ItemReportFacts($workspace))->itemFacts($item->id)['units_3d'])->toBe(12);
});

test('nothing is written when no unit code can attribute an order to an item', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 25, 'unfulfilled_count' => 40,
    ]);

    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    expect((new GencysDemandSync($workspace))->run())->toBe(0);

    // Zeroing here would say "nothing is owed" when the truth is "we cannot
    // tell" — and the reorder maths would stop subtracting real demand.
    expect($item->fresh()->unfulfilled_count)->toBe(40);
});

test('the snapshot freezes the demand it measured this run', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    // Counted once, by the run that froze it — 9 units over the window, which
    // the list divides into 3 a day.
    expect((int) InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->where('snapshot_date', now()->toDateString())
        ->value('units_3d'))->toBe(9);
});

test('a workspace without ERP credentials is snapshotted but not recomputed', function () {
    $workspace = gencysWorkspace(['erp_username' => null, 'erp_password' => null]);
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'unfulfilled_count' => 12,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    // What the sync owns is left exactly as it was.
    expect($item->fresh()->unfulfilled_count)->toBe(12)
        ->and((int) InventoryItemSnapshot::where('inventory_item_id', $item->id)
            ->where('snapshot_date', now()->toDateString())
            ->value('unfulfilled_count'))->toBe(12);
});

test('--skip-demand freezes what is stored without recomputing', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'unfulfilled_count' => 7,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    // An open order the sync would otherwise pick up.
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString(), 'ENCODED');

    $this->artisan('inventory:snapshot-items', ['--skip-demand' => true])->assertSuccessful();

    expect($item->fresh()->unfulfilled_count)->toBe(7);
});

test('a partial refresh freezes rows without moving the whole workspace demand', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'unfulfilled_count' => 5,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $snapshotter = new InventoryItemSnapshotter($workspace, now()->toDateString());

    // Naming items is what an edit does — it is patching a few rows, not taking
    // the day. Recomputing demand here would move every other figure on the page
    // as a side effect of someone changing one field, and make the edit wait for
    // a pass over the order feed.
    $snapshotter->refresh([$item->id]);

    expect($snapshotter->demandSynced())->toBe(0)
        ->and($item->fresh()->unfulfilled_count)->toBe(5);

    // A full refresh does own the day, and does recompute.
    $full = new InventoryItemSnapshotter($workspace, now()->toDateString());
    $full->refresh();

    expect($full->demandSynced())->toBe(1)
        // No open orders in the fixture, so what was standing is cleared.
        ->and($item->fresh()->unfulfilled_count)->toBe(0);
});

test('a group demand is exactly the sum of its items', function () {
    $workspace = gencysWorkspace();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'CHILD-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'CHILD-B', 'parent_id' => $parent->id, 'is_active' => true]);

    // Quantities chosen so the per-child rates are fractional: 2/3 and 5/3 a
    // day against a group of 7/3.
    demandUnitCode($workspace, 'BUNDLE-A', ['CHILD-A' => 2, 'CHILD-B' => 5]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $facts = new ItemReportFacts($workspace);

    // The property the roll-up depends on: summing the items reproduces the
    // group exactly. It is what lets a filter narrow the demand to the rows it
    // leaves on screen, and what makes one count enough for both grains.
    $items = $facts->itemFacts($a->id)['units_3d'] + $facts->itemFacts($b->id)['units_3d'];

    expect($items)->toBe(7)
        ->and($facts->for($parent->id)['units_3d'])->toBe($items)
        // A parent carries none of its own; the group's demand is its children's.
        ->and($facts->itemFacts($parent->id)['units_3d'])->toBe(0);
});

test('a three-day window covers three days, not four', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 3]);

    // One order on each of four consecutive days, the latest being the feed's.
    foreach ([0, 1, 2, 3] as $daysBack) {
        demandOrder($workspace, 'BUNDLE-A', now()->subDays($daysBack)->toDateString());
    }

    // Three days of orders, three units each. Counting the fourth day and still
    // dividing by three would read 4 a day instead of 3.
    expect((new ItemReportFacts($workspace))->itemFacts($item->id)['units_3d'])->toBe(9);
});
