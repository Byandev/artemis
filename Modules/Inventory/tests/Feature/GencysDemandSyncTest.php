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

    // 3 in-window orders x 3 per bundle = 9 widgets over 3 days.
    expect((float) $a->fresh()->three_days_average)->toBe(3.0)
        ->and((float) $b->fresh()->three_days_average)->toBe(1.0)
        // One order is open: 3 widgets, 1 gadget.
        ->and($a->fresh()->unfulfilled_count)->toBe(3)
        ->and($b->fresh()->unfulfilled_count)->toBe(1);
});

test('the average is measured from the feed, not from today', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 6]);

    // The whole feed stopped a fortnight ago. Anchored to today this would find
    // nothing and write a zero average, which the list reads as "not selling" —
    // and turns into infinite cover and a PO Needed of nothing.
    demandOrder($workspace, 'BUNDLE-A', now()->subDays(14)->toDateString());
    demandOrder($workspace, 'BUNDLE-A', now()->subDays(15)->toDateString());

    (new GencysDemandSync($workspace))->run();

    expect((float) $item->fresh()->three_days_average)->toBe(4.0);
});

test('nothing is written when no unit code can attribute an order to an item', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 25, 'unfulfilled_count' => 40,
    ]);

    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    expect((new GencysDemandSync($workspace))->run())->toBe(0);

    // Zeroing here would say "nothing is selling" when the truth is "we cannot
    // tell" — and every cover figure on the page would follow it down.
    expect((float) $item->fresh()->three_days_average)->toBe(25.0)
        ->and($item->fresh()->unfulfilled_count)->toBe(40);
});

test('the snapshot recomputes demand first, then freezes it', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 0,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    // Frozen with the demand this run computed, not the previous run's zero —
    // the whole reason the two live in one command.
    expect((float) $item->fresh()->three_days_average)->toBe(3.0)
        ->and((float) InventoryItemSnapshot::where('inventory_item_id', $item->id)
            ->where('snapshot_date', now()->toDateString())
            ->value('three_days_average'))->toBe(3.0);
});

test('a workspace without ERP credentials is snapshotted but not recomputed', function () {
    $workspace = gencysWorkspace(['erp_username' => null, 'erp_password' => null]);
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 12,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    // Left exactly as it was, and still frozen for the day.
    expect((float) $item->fresh()->three_days_average)->toBe(12.0)
        ->and((float) InventoryItemSnapshot::where('inventory_item_id', $item->id)
            ->where('snapshot_date', now()->toDateString())
            ->value('three_days_average'))->toBe(12.0);
});

test('--skip-demand freezes what is stored without recomputing', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 7,
    ]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 9]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $this->artisan('inventory:snapshot-items', ['--skip-demand' => true])->assertSuccessful();

    expect((float) $item->fresh()->three_days_average)->toBe(7.0);
});

test('a partial refresh freezes rows without moving the whole workspace demand', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'WIDGET',
        'is_active' => true, 'three_days_average' => 5,
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
        ->and((float) $item->fresh()->three_days_average)->toBe(5.0);

    // A full refresh does own the day, and does recompute.
    $full = new InventoryItemSnapshotter($workspace, now()->toDateString());
    $full->refresh();

    expect($full->demandSynced())->toBe(1)
        ->and((float) $item->fresh()->three_days_average)->toBe(3.0);
});

test('the stored 3-day average equals the report units-per-day, group and all', function () {
    $workspace = gencysWorkspace();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'CHILD-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'CHILD-B', 'parent_id' => $parent->id, 'is_active' => true]);

    // Quantities chosen so per-child averages are fractional: rounding each up
    // before summing would make the group read 4/day against a true 7/3.
    demandUnitCode($workspace, 'BUNDLE-A', ['CHILD-A' => 2, 'CHILD-B' => 5]);
    demandOrder($workspace, 'BUNDLE-A', now()->toDateString());

    (new GencysDemandSync($workspace))->run();
    $facts = (new ItemReportFacts($workspace))->for($parent->id);

    $stored = (float) $a->fresh()->three_days_average + (float) $b->fresh()->three_days_average;
    $reported = $facts['units_3d'] / 3;

    // Two figures shown side by side on the same row, from the same orders, so
    // they have to be one number rather than two that nearly agree.
    //
    // Compared to a thousandth rather than exactly: three_days_average is stored
    // per item in decimal(10,4), so summing a group's children can land a single
    // ten-thousandth away from dividing the group's own demand. That is the
    // column's precision, not a difference in the maths — the page renders one
    // decimal place. Rounding each child up, which is what this replaced, put
    // the two a third apart.
    expect(abs($stored - $reported))->toBeLessThan(0.001)
        ->and(abs($stored - 7 / 3))->toBeLessThan(0.001);
});

test('a three-day window covers three days, not four', function () {
    $workspace = gencysWorkspace();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'WIDGET', 'is_active' => true]);
    demandUnitCode($workspace, 'BUNDLE-A', ['WIDGET' => 3]);

    // One order on each of four consecutive days, the latest being the feed's.
    foreach ([0, 1, 2, 3] as $daysBack) {
        demandOrder($workspace, 'BUNDLE-A', now()->subDays($daysBack)->toDateString());
    }

    (new GencysDemandSync($workspace))->run();

    // Three days of orders, three units each, over three days. Counting the
    // fourth day and still dividing by three would read 4.
    expect((float) $item->fresh()->three_days_average)->toBe(3.0)
        ->and((new ItemReportFacts($workspace))->for($item->id)['units_3d'])->toBe(9);
});
