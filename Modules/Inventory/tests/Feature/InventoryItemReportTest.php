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

    $order = function (int $status, string $issuedAt, int $count, int $delivered = 0, ?string $expected = null) use ($workspace, $item) {
        $po = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'control_no' => 'CN-'.uniqid(),
            'issue_date' => $issuedAt,
            'expected_delivery_date' => $expected,
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

    // Raised 20 days ago and never released — nobody has told a supplier to start.
    $order(1, now()->subDays(20)->toDateString(), 500);
    // Released 30 days ago, well past the 14-day delivery target, part-delivered.
    $order(6, now()->subDays(30)->toDateString(), 400, delivered: 100, expected: now()->addDays(5)->toDateString());
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
        ->and($facts['earliest_expected_date'])->toBe(now()->addDays(5)->toDateString())
        ->and($facts['earliest_expected_count'])->toBe(300)
        // One order past the target, counted per order rather than per line.
        ->and($facts['delayed_po'])->toBe(1)
        // 900 units sit with suppliers against 500 held for approval, so that
        // is where this group's stock is stuck.
        ->and($facts['bottleneck_stage'])->toBe('Waiting For Delivery');
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

test('the report downloads as a spreadsheet with every column', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    reportItem($workspace, 'WIDGET');

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/inventory/items/report");

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $headings = (new InventoryItemReportExport(
        DB::query()->selectRaw('1'),
        new ItemReportFacts($workspace),
    ))->headings();

    expect($headings)->toHaveCount(31)
        ->and($headings[0])->toBe('Item')
        ->and($headings[7])->toBe('Demand Trend (3d vs 14d)')
        ->and(end($headings))->toBe('Bottleneck Stage');
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
    $export = new InventoryItemReportExport(
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
