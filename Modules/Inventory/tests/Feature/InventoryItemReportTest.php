<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exports\InventoryItemReportExport;
use Modules\Inventory\Http\Controllers\InventoryItemController;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Support\ItemReportFacts;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** An item with a product behind it, so the summary roll-up can reach it. */
function reportItem(Workspace $workspace, string $sku, float $dailyAverage = 10): InventoryItem
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id]);
    Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);

    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'is_active' => true,
        'three_days_average' => $dailyAverage,
        'lead_time' => 10,
        'days_of_coverage' => 10,
    ]);
}

/**
 * Insert a Gencys order and return its id.
 *
 * The id is supplied rather than generated: gencys_orders.id carries no
 * auto-increment, because the row is the ERP's record and keeps the ERP's key.
 */
function gencysOrderRow(Workspace $workspace, string $date): int
{
    static $nextId = 1;
    $id = $nextId++;

    DB::table('gencys_orders')->insert([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'order_no' => 'GO-'.$id,
        'order_date' => $date,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * A Gencys order whose single line names $unitCode, dated $date.
 *
 * The line's own quantity is deliberately not 1 — the expansion must ignore it
 * and use the unit code's component quantities, exactly as the demand sync does.
 */
function gencysOrder(Workspace $workspace, string $unitCode, string $date, int $lineQty = 99): void
{
    DB::table('gencys_order_items')->insert([
        'order_id' => gencysOrderRow($workspace, $date),
        'sku' => $unitCode,
        'quantity' => $lineQty,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** A unit code that expands into the given SKU => qty-per-bundle components. */
function unitCode(Workspace $workspace, string $code, array $components): void
{
    DB::table('inventory_unit_codes')->insert([
        'workspace_id' => $workspace->id,
        'unit_code' => $code,
        'sku' => $code,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($components as $itemCode => $qty) {
        DB::table('inventory_unit_code_items')->insert([
            'workspace_id' => $workspace->id,
            'unit_code' => $code,
            'item_code' => $itemCode,
            'quantity' => $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * A frozen snapshot row for the item on $date, with defaults that carry a real
 * reorder need (10/day against a 10-day cover and 10-day lead, nothing incoming)
 * and no shippable stock. Override any of it through $cols — e.g. stock and
 * unfulfilled for the warehouse cases, or a large remaining_after_fulfillment to
 * close the reorder gap.
 */
function reportSnapshot(Workspace $workspace, InventoryItem $item, string $date, array $cols = []): void
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
        'three_days_average' => 10,
        'unfulfilled_count' => 0,
        'current_stocks' => 0,
        'remaining_after_fulfillment' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $cols));
}

test('demand windows expand unit codes into component units and count orders once', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');
    // One bundle carries three of the widget, so one order line is three units.
    unitCode($workspace, 'BUNDLE-A', ['WIDGET' => 3]);

    // Two orders inside the 3-day window, one older that only the 14-day sees.
    gencysOrder($workspace, 'BUNDLE-A', now()->toDateString());
    gencysOrder($workspace, 'BUNDLE-A', now()->subDay()->toDateString());
    gencysOrder($workspace, 'BUNDLE-A', now()->subDays(10)->toDateString());

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['orders_3d'])->toBe(2)
        // The line quantity of 99 is ignored; the component quantity is not.
        ->and($facts['units_3d'])->toBe(6)
        ->and($facts['orders_14d'])->toBe(3)
        ->and($facts['units_14d'])->toBe(9);
});

test('one order spanning two bundles of the same item counts as a single order', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');
    unitCode($workspace, 'BUNDLE-A', ['WIDGET' => 2]);

    $id = gencysOrderRow($workspace, now()->toDateString());

    // Two lines, same order — two bundles to pick, but still one order.
    foreach ([1, 2] as $n) {
        DB::table('gencys_order_items')->insert([
            'order_id' => $id, 'sku' => 'BUNDLE-A', 'quantity' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['orders_3d'])->toBe(1)
        ->and($facts['units_3d'])->toBe(4);
});

test('demand windows are measured from the feed, not from today', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');
    unitCode($workspace, 'BUNDLE-A', ['WIDGET' => 1]);

    // The whole feed stopped a fortnight ago. Anchored to today every window
    // would be empty, which would read as "demand collapsed" rather than
    // "nobody has sent us any orders lately".
    gencysOrder($workspace, 'BUNDLE-A', now()->subDays(14)->toDateString());
    gencysOrder($workspace, 'BUNDLE-A', now()->subDays(15)->toDateString());

    $report = new ItemReportFacts($workspace);

    expect($report->demandAsOf()->toDateString())->toBe(now()->subDays(14)->toDateString())
        ->and($report->for($item->id)['orders_3d'])->toBe(2);
});

test('demand rolls up to the group across its children', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = reportItem($workspace, 'PARENT');
    $parent->update(['is_parent' => true]);

    $childA = reportItem($workspace, 'CHILD-A');
    $childB = reportItem($workspace, 'CHILD-B');
    $childA->update(['parent_id' => $parent->id]);
    $childB->update(['parent_id' => $parent->id]);

    unitCode($workspace, 'BUNDLE-A', ['CHILD-A' => 2, 'CHILD-B' => 5]);
    gencysOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $facts = (new ItemReportFacts($workspace))->for($parent->id);

    // Both children are demand against the same group's supply.
    expect($facts['units_3d'])->toBe(7)
        ->and($facts['orders_3d'])->toBe(1);
});

test('movement facts report the quantity that moved on the last in and out days', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    $tx = fn (string $date, string $ref, array $cols) => $item->transactions()->create(array_merge([
        'workspace_id' => $workspace->id, 'date' => $date, 'ref_no' => $ref,
    ], $cols));

    $tx(now()->subDays(9)->toDateString(), 'TX-1', ['po_qty_in' => 100, 'remaining_qty' => 100]);
    $tx(now()->subDays(4)->toDateString(), 'TX-2', ['po_qty_out' => 30, 'remaining_qty' => 70]);
    // Two receipts on the same day are one arrival of 250.
    $tx(now()->subDays(2)->toDateString(), 'TX-3', ['po_qty_in' => 200, 'remaining_qty' => 270]);
    $tx(now()->subDays(2)->toDateString(), 'TX-4', ['po_qty_in' => 50, 'remaining_qty' => 320]);
    // RTS movements are not purchase-order movements and must not count.
    $tx(now()->toDateString(), 'TX-5', ['rts_goods_out' => 10, 'remaining_qty' => 310]);

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['last_in_date'])->toBe(now()->subDays(2)->toDateString())
        ->and($facts['last_in_count'])->toBe(250)
        ->and($facts['last_out_date'])->toBe(now()->subDays(4)->toDateString())
        ->and($facts['last_out_count'])->toBe(30);
});

test('purchase-order facts separate what we have not released from what a supplier is sitting on', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    // Written the way the sync writes them: an order with no stated expected
    // date gets the standing two-week agreement stored against it.
    $order = function (int $status, string $issuedAt, int $count, int $delivered = 0, ?string $expected = null) use ($workspace, $item) {
        $po = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'control_no' => 'CN-'.uniqid(),
            'issue_date' => $issuedAt,
            'expected_delivery_date' => PurchasedOrder::expectedDeliveryFor($expected, $issuedAt),
            'status' => $status,
        ]);

        $line = PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'count' => $count,
        ]);

        if ($delivered > 0) {
            $line->deliveries()->create(['delivery_date' => $issuedAt, 'qty' => $delivered]);
        }
    };

    // Raised 20 days ago and never released — nobody has told a supplier to
    // start. Its stored expected date is 20 - 14 = 6 days past due.
    $order(1, now()->subDays(20)->toDateString(), 500);
    // Released 30 days ago and part-delivered, with the supplier's own
    // commitment already a fortnight behind.
    $order(6, now()->subDays(30)->toDateString(), 400, delivered: 100, expected: now()->subDays(16)->toDateString());
    // Released two days ago: with a supplier, but not late. Sized so the
    // released pair clearly outweighs the unreleased one and the bottleneck
    // stage is not decided by a tie-break.
    $order(6, now()->subDays(2)->toDateString(), 600);

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['raised_not_created_days'])->toBe(20)
        ->and($facts['raised_not_created_units'])->toBe(500)
        // Most recent order raised, and the balance it still owes.
        ->and($facts['last_po_date'])->toBe(now()->subDays(2)->toDateString())
        ->and($facts['last_po_count'])->toBe(600)
        // Longest-waiting is the 30-day-old one, owing 300 of its 400.
        ->and($facts['longest_waiting_date'])->toBe(now()->subDays(30)->toDateString())
        ->and($facts['longest_waiting_count'])->toBe(300)
        // Earliest of the three stored dates is the supplier's missed
        // commitment, 16 days ago, against the 300 units it still owes.
        ->and($facts['earliest_expected_date'])->toBe(now()->subDays(16)->toDateString())
        ->and($facts['earliest_expected_count'])->toBe(300)
        // Only that one is both released and past its date — the 6-days-overdue
        // order is still sitting in approval, which is our delay, not theirs.
        ->and($facts['delayed_po'])->toBe(1)
        // 500 units have sat un-paid for 20 days against 300 the supplier is
        // late on, and with no snapshots here neither the Late-PO nor the
        // warehouse owner can weigh in — so the internal queue is the biggest
        // pile, and the bottleneck is ours.
        ->and($facts['bottleneck_stage'])->toBe('Delay in Payment / Approval');
});

test('an order raised with no expected date is stored as due two weeks later', function () {
    expect(PurchasedOrder::expectedDeliveryFor(null, '2026-08-01'))->toBe('2026-08-15')
        // An explicit commitment is kept, however short.
        ->and(PurchasedOrder::expectedDeliveryFor('2026-08-04', '2026-08-01'))->toBe('2026-08-04')
        // Nothing to count from, so nothing is claimed.
        ->and(PurchasedOrder::expectedDeliveryFor(null, null))->toBeNull();
});

test('the report reads the expected date the order carries', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'CN-1',
        'issue_date' => now()->subDays(3)->toDateString(),
        'expected_delivery_date' => PurchasedOrder::expectedDeliveryFor(null, now()->subDays(3)),
        'status' => 6,
    ]);

    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 250,
    ]);

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['earliest_expected_date'])->toBe(now()->addDays(11)->toDateString())
        ->and($facts['earliest_expected_count'])->toBe(250)
        // Eleven days still to run, so nobody is late yet.
        ->and($facts['delayed_po'])->toBe(0);
});

test('a supplier is late against its own commitment, not a fixed age', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    $order = function (string $issuedAt, ?string $expected) use ($workspace, $item) {
        $po = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'control_no' => 'CN-'.uniqid(),
            'issue_date' => $issuedAt,
            'expected_delivery_date' => $expected,
            'status' => 6,
        ]);

        PurchasedOrderItem::create([
            'inventory_purchased_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'count' => 100,
        ]);
    };

    // Raised only four days ago, but the supplier promised it in two — late,
    // even though a fixed 14-day age would call it perfectly healthy.
    $order(now()->subDays(4)->toDateString(), now()->subDays(2)->toDateString());
    // Raised a month ago with a commitment still a week out: not late.
    $order(now()->subDays(30)->toDateString(), now()->addDays(7)->toDateString());

    expect((new ItemReportFacts($workspace))->for($item->id)['delayed_po'])->toBe(1);
});

test('a fully delivered order is not still waiting on anyone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'CN-1',
        'issue_date' => now()->subDays(40)->toDateString(),
        'status' => 6,
    ]);

    $line = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 100,
    ]);
    $line->deliveries()->create(['delivery_date' => now()->subDays(5)->toDateString(), 'qty' => 100]);

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['last_po_date'])->toBeNull()
        ->and($facts['delayed_po'])->toBe(0)
        ->and($facts['bottleneck_stage'])->toBeNull();
});

test('a group with no orders, movements or purchase orders still reports a full row', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'QUIET');

    $facts = (new ItemReportFacts($workspace))->for($item->id);

    expect($facts['orders_3d'])->toBe(0)
        ->and($facts['units_14d'])->toBe(0)
        ->and($facts['last_in_date'])->toBeNull()
        ->and($facts['delayed_po'])->toBe(0)
        ->and($facts['bottleneck_stage'])->toBeNull();
});

test('the export downloads as a spreadsheet with every column', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    reportItem($workspace, 'WIDGET');

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/inventory/items/export");

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $headings = InventoryItemReportExport::live(
        DB::query()->selectRaw('1'),
        new ItemReportFacts($workspace),
    )->headings();

    expect($headings)->toHaveCount(31)
        ->and($headings[0])->toBe('Item')
        ->and($headings[7])->toBe('Demand Trend (3d vs 14d)')
        ->and(end($headings))->toBe('Bottleneck Stage');
});

test('the snapshot freezes the report figures and a past date reads them back', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');
    $item->update(['unfulfilled_count' => 40]);
    unitCode($workspace, 'BUNDLE-A', ['WIDGET' => 5]);
    gencysOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $item->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->subDays(2)->toDateString(),
        'ref_no' => 'TX-1', 'po_qty_in' => 300, 'remaining_qty' => 300,
    ]);

    $this->artisan('inventory:snapshot-items', ['--date' => now()->toDateString(), '--force' => true])->assertSuccessful();

    $frozen = DB::table('inventory_item_snapshots')
        ->where('inventory_item_id', $item->id)
        ->where('snapshot_date', now()->toDateString())
        ->first();

    expect((int) $frozen->units_3d)->toBe(5)
        ->and((int) $frozen->orders_3d)->toBe(1)
        ->and($frozen->last_in_date)->toBe(now()->subDays(2)->toDateString())
        ->and((int) $frozen->last_in_count)->toBe(300)
        ->and($frozen->demand_as_of)->toBe(now()->toDateString());

    // Now move the world on: the order feed and the ledger both change. A pinned
    // date must keep answering for the day it names, not re-answer for today.
    gencysOrder($workspace, 'BUNDLE-A', now()->toDateString());
    gencysOrder($workspace, 'BUNDLE-A', now()->toDateString());

    $controller = new InventoryItemController;
    $method = new ReflectionMethod($controller, 'buildSnapshotSummaryQuery');
    $request = Request::create('/');
    $request->setUserResolver(fn () => $owner);

    $rows = iterator_to_array(
        InventoryItemReportExport::asOf(
            $method->invoke($controller, $request, $workspace, now()->toDateString())
        )->generator()
    );

    // Column 2 is the 3-day daily unit rate: 5 units over 3 days as frozen,
    // shown as whole units rounded up.
    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe('WIDGET')
        ->and($rows[0][2])->toBe((int) ceil(5 / 3))
        ->and($rows[0][14])->toBe(now()->subDays(2)->toDateString());

    // Live, the same report now sees all three orders.
    $live = iterator_to_array(
        InventoryItemReportExport::live(
            (new ReflectionMethod($controller, 'buildSummaryQuery'))->invoke($controller, $request, $workspace),
            new ItemReportFacts($workspace),
        )->generator()
    );

    expect($live[0][2])->toBe((int) ceil(15 / 3));
});

test('a snapshot taken before the report existed reports unknown, not zero', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    $this->artisan('inventory:snapshot-items', ['--date' => now()->toDateString(), '--force' => true])->assertSuccessful();

    // Blank the frozen figures, as an older snapshot row would have them.
    DB::table('inventory_item_snapshots')
        ->where('inventory_item_id', $item->id)
        ->update(array_fill_keys(ItemReportFacts::SNAPSHOT_COLUMNS, null));

    $controller = new InventoryItemController;
    $method = new ReflectionMethod($controller, 'buildSnapshotSummaryQuery');
    $request = Request::create('/');
    $request->setUserResolver(fn () => $owner);

    $rows = iterator_to_array(
        InventoryItemReportExport::asOf(
            $method->invoke($controller, $request, $workspace, now()->toDateString())
        )->generator()
    );

    // Demand, trend and the movement dates are unknown rather than nil — that
    // day recorded nothing, which is not the same as recording that nothing
    // happened.
    expect($rows[0][1])->toBeNull()
        ->and($rows[0][2])->toBeNull()
        ->and($rows[0][7])->toBeNull()
        ->and($rows[0][14])->toBeNull()
        // Stock columns were always frozen, so they still answer.
        ->and($rows[0][10])->toBe(0);
});

test('stockout risk is read against the lead time, not a fixed number of days', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // 10 units a day against a 10-day lead time. 40 units is 4 days of cover —
    // under half the lead time, so ordering now would already be too late.
    $risky = reportItem($workspace, 'RISKY', dailyAverage: 10);
    $risky->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->toDateString(),
        'ref_no' => 'TX-1', 'remaining_qty' => 40,
    ]);

    // Same lead time, 200 units: twenty days of cover, comfortably clear.
    $safe = reportItem($workspace, 'SAFE', dailyAverage: 10);
    $safe->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->toDateString(),
        'ref_no' => 'TX-2', 'remaining_qty' => 200,
    ]);

    $controller = new InventoryItemController;
    $method = new ReflectionMethod($controller, 'buildSummaryQuery');
    $request = Request::create('/');
    $request->setUserResolver(fn () => $owner);

    $rows = collect($method->invoke($controller, $request, $workspace)->get())->keyBy('sku');
    $export = InventoryItemReportExport::live(
        $method->invoke($controller, $request, $workspace),
        new ItemReportFacts($workspace),
    );

    $bySku = collect(iterator_to_array($export->generator()))->keyBy(0);

    expect($rows)->toHaveCount(2)
        // Column 13 is Stockout Risk, column 11 Current Stocks Can Last.
        ->and($bySku['RISKY'][11])->toBe(4.0)
        ->and($bySku['RISKY'][13])->toBe('Critical')
        ->and($bySku['SAFE'][11])->toBe(20.0)
        ->and($bySku['SAFE'][13])->toBe('OK');
});

test('Late PO fires when a reorder need goes unraised for more than three days', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    // A real reorder gap frozen on five of the last fourteen days, with no
    // purchase order ever raised against it — the officer has not acted.
    foreach ([1, 2, 3, 4, 5] as $daysAgo) {
        reportSnapshot($workspace, $item, now()->subDays($daysAgo)->toDateString());
    }

    expect((new ItemReportFacts($workspace))->for($item->id)['bottleneck_stage'])
        ->toBe('Late PO');
});

test('a persisting gap is not Late PO once a purchase order has been raised', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    foreach ([1, 2, 3, 4, 5] as $daysAgo) {
        reportSnapshot($workspace, $item, now()->subDays($daysAgo)->toDateString());
    }

    // The officer did raise one two days ago — released, nothing yet overdue.
    // Closing the gap is now someone else's job, not a creation failure, so no
    // owner is past its target and the row stays blank.
    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'CN-1',
        'issue_date' => now()->subDays(2)->toDateString(),
        'status' => 6,
    ]);
    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 200,
    ]);

    expect((new ItemReportFacts($workspace))->for($item->id)['bottleneck_stage'])
        ->toBeNull();
});

test('the warehouse owns the hold-up when shippable stock sits unshipped', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    // Shipped five days ago; a receipt since moved the ledger on, so the shelf
    // has stood four days without a despatch — past the two-day target.
    $item->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->subDays(5)->toDateString(),
        'ref_no' => 'TX-OUT', 'po_qty_out' => 30, 'remaining_qty' => 70,
    ]);
    $item->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->subDays(1)->toDateString(),
        'ref_no' => 'TX-IN', 'po_qty_in' => 100, 'remaining_qty' => 170,
    ]);

    // 100 on hand against 40 unmet, so 40 could ship. A large incoming figure
    // closes the reorder gap, keeping Late PO out of it.
    reportSnapshot($workspace, $item, now()->subDays(1)->toDateString(), [
        'current_stocks' => 100, 'unfulfilled_count' => 40,
        'remaining_after_fulfillment' => 100000,
    ]);

    expect((new ItemReportFacts($workspace))->for($item->id)['bottleneck_stage'])
        ->toBe('Delay in warehouse');
});

test('a late supplier outranks a smaller idle shelf', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = reportItem($workspace, 'WIDGET');

    // Released a month ago, expected a fortnight ago, still owes 300.
    $po = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'CN-1',
        'issue_date' => now()->subDays(30)->toDateString(),
        'expected_delivery_date' => now()->subDays(14)->toDateString(),
        'status' => 6,
    ]);
    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'count' => 300,
    ]);

    // A small idle shelf as well: 40 shippable, past the despatch target.
    $item->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->subDays(5)->toDateString(),
        'ref_no' => 'TX-OUT', 'po_qty_out' => 10, 'remaining_qty' => 90,
    ]);
    $item->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->subDays(1)->toDateString(),
        'ref_no' => 'TX-IN', 'po_qty_in' => 10, 'remaining_qty' => 100,
    ]);
    reportSnapshot($workspace, $item, now()->subDays(1)->toDateString(), [
        'current_stocks' => 100, 'unfulfilled_count' => 40,
        'remaining_after_fulfillment' => 100000,
    ]);

    // 300 units late from the supplier beat 40 idle on the shelf.
    expect((new ItemReportFacts($workspace))->for($item->id)['bottleneck_stage'])
        ->toBe('Delay in stocks');
});
