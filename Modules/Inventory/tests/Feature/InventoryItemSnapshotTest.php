<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemExport;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Modules\Inventory\Models\InventoryTransaction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Give an item a ledger stock via its latest transaction's running remaining_qty. */
function ledger(InventoryItem $item, int $remaining, string $date = '2026-06-01'): void
{
    InventoryTransaction::create([
        'workspace_id' => $item->workspace_id,
        'inventory_item_id' => $item->id,
        'date' => $date,
        'ref_no' => 'TXN-'.$item->id.'-'.$remaining,
        'remaining_qty' => $remaining,
    ]);
}

/** Rows the items list renders, for a given query string. */
function listRows($owner, $workspace, string $qs = ''): array
{
    $rows = [];
    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).$qs)
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows) {
            $rows = $page->toArray()['props']['items']['data'];
        });

    return $rows;
}

test('the snapshot command stores every item column and computed metric', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Widget']);

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => 'SKU-1',
        'is_active' => true,
        'lead_time' => 5,
        'three_days_average' => 4,
        'unfulfilled_count' => 2,
    ]);
    ledger($item, 30);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01'])
        ->assertExitCode(0);

    $snap = InventoryItemSnapshot::where('inventory_item_id', $item->id)->firstOrFail();

    // Stored columns copied verbatim...
    expect($snap->sku)->toBe('SKU-1')
        ->and($snap->lead_time)->toBe(5)
        ->and($snap->unfulfilled_count)->toBe(2)
        ->and($snap->is_active)->toBeTrue()
        ->and($snap->product_id)->toBe($product->id)
        ->and($snap->product_name)->toBe('Widget')
        ->and($snap->snapshot_date->toDateString())->toBe('2026-08-01');

    // ...and the derived metrics as the list computes them:
    // current 30, remaining_after_fulfillment 30 - 2 unfulfilled = 28,
    // stocks needed 5 × 4 = 20, po_needed max(0, 20 - 28) = 0.
    expect((int) $snap->current_stocks)->toBe(30)
        ->and((int) $snap->remaining_after_fulfillment)->toBe(28)
        ->and((int) $snap->stocks_needed_for_lead_time)->toBe(20)
        ->and((int) $snap->po_needed)->toBe(0);
});

test('re-running the command for a date refreshes rather than duplicates', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 10);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    // Stock moves, then the same date is snapshotted again.
    ledger($item, 99, '2026-06-02');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    $snaps = InventoryItemSnapshot::where('inventory_item_id', $item->id)->get();
    expect($snaps)->toHaveCount(1)
        ->and((int) $snaps->first()->current_stocks)->toBe(99);
});

test('each day keeps its own row so history builds up', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    ledger($item, 4, '2026-06-02');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-02']);

    $byDate = InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->pluck('current_stocks', 'snapshot_date');

    expect((int) $byDate['2026-08-01'])->toBe(10)
        ->and((int) $byDate['2026-08-02'])->toBe(4);
});

test('the date filter shows the snapshot for that day, not live stock', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    // Stock changes after the snapshot was taken.
    ledger($item, 77, '2026-06-02');

    $live = listRows($owner, $workspace, '?summarize=0');
    expect((int) collect($live)->firstWhere('sku', 'SKU-1')['current_stocks'])->toBe(77);

    $past = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01');
    expect((int) collect($past)->firstWhere('sku', 'SKU-1')['current_stocks'])->toBe(10);
});

test('the summarize roll-up works against a snapshot date', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    ledger($a, 30);
    ledger($b, 12);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    $rows = listRows($owner, $workspace, '?summarize=1&filter[date]=2026-08-01');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['sku'])->toBe('GROUP')
        ->and((int) $rows[0]['id'])->toBe($parent->id)
        ->and((int) $rows[0]['is_group'])->toBe(1)
        ->and((int) $rows[0]['child_count'])->toBe(2)
        // The group sums its children exactly as the live roll-up does.
        ->and((int) $rows[0]['current_stocks'])->toBe(42);
});

test('the flat snapshot view tags each child with the parent it had that day', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    $rows = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01');

    // Parents are hidden in the flat view, same as the live list.
    expect($rows)->toHaveCount(1);
    expect($rows[0]['sku'])->toBe('SUP-A')
        ->and($rows[0]['parent_sku'])->toBe('GROUP');
});

test('search and sort still apply on a snapshot date', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach ([['ALPHA', 5], ['BETA', 50], ['ALPHA-2', 1]] as [$sku, $stock]) {
        $i = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => $sku, 'is_active' => true]);
        ledger($i, $stock);
    }

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    $searched = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01&filter[search]=ALPHA');
    expect(collect($searched)->pluck('sku')->sort()->values()->all())->toBe(['ALPHA', 'ALPHA-2']);

    $sorted = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01&sort=-current_stocks');
    expect(collect($sorted)->pluck('sku')->first())->toBe('BETA');
});

test('a date with no snapshot falls back to live data', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 55);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=0&filter[date]=2019-01-01')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            // No snapshot for that date -> the banner stays off and stock reads live.
            expect($props['snapshotDate'])->toBeNull();
            expect((int) $props['items']['data'][0]['current_stocks'])->toBe(55);
        });
});

test('the list reports which dates have snapshots', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-03']);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?filter[date]=2026-08-03')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];
            expect($props['snapshotDate'])->toBe('2026-08-03');
            expect($props['snapshotDates'])->toBe(['2026-08-03', '2026-08-01']);
        });
});

test('one workspace never sees another workspace snapshots', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    InventoryItem::create(['workspace_id' => $workspaceB->id, 'sku' => 'B-ONLY', 'is_active' => true]);
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspaceA).'?summarize=0&filter[date]=2026-08-01')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            // A snapshot exists for the date, but not in this workspace.
            expect($page->toArray()['props']['snapshotDate'])->toBeNull();
            expect($page->toArray()['props']['items']['data'])->toBeEmpty();
        });
});

test('the export follows the pinned date instead of exporting today', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Widget']);
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => 'SKU-1',
        'is_active' => true,
    ]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01']);
    ledger($item, 77, '2026-06-02');

    Excel::fake();

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.export', $workspace).'?summarize=0&filter[date]=2026-08-01')
        ->assertOk();

    Excel::assertDownloaded('inventory-items-2026-08-01.xlsx', function (InventoryItemExport $export) {
        $rows = iterator_to_array($export->generator());

        // SKU, Product, Status, Lead Time, Unfulfilled, Remaining Qty(current_stocks)…
        expect($rows[0][0])->toBe('SKU-1')
            ->and($rows[0][1])->toBe('Widget')
            ->and((int) $rows[0][5])->toBe(10);

        return true;
    });
});

test('the workspace option limits the snapshot to one workspace', function () {
    ['workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    InventoryItem::create(['workspace_id' => $workspaceA->id, 'sku' => 'A-1', 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspaceB->id, 'sku' => 'B-1', 'is_active' => true]);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--workspace' => $workspaceA->id]);

    expect(InventoryItemSnapshot::pluck('sku')->all())->toBe(['A-1']);
});
