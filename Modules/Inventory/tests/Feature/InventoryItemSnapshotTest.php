<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemReportExport;
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
        'days_of_coverage' => 10,
        'three_days_average' => 4,
        'unfulfilled_count' => 2,
    ]);
    ledger($item, 30);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true])
        ->assertExitCode(0);

    $snap = InventoryItemSnapshot::where('inventory_item_id', $item->id)->firstOrFail();

    // Stored columns copied verbatim...
    expect($snap->sku)->toBe('SKU-1')
        ->and($snap->lead_time)->toBe(5)
        ->and($snap->days_of_coverage)->toBe(10)
        ->and($snap->unfulfilled_count)->toBe(2)
        ->and($snap->is_active)->toBeTrue()
        ->and($snap->product_id)->toBe($product->id)
        ->and($snap->product_name)->toBe('Widget')
        ->and($snap->snapshot_date->toDateString())->toBe('2026-08-01');

    // ...and the derived metrics as the list computes them:
    // current 30, remaining_after_fulfillment 30 - 2 unfulfilled = 28,
    // stocks needed 5 × 4 = 20, po_qty (buffer) 10 × 4 = 40,
    // po_needed max(0, 40 + 20 - 28) = 32.
    expect((int) $snap->current_stocks)->toBe(30)
        ->and((int) $snap->remaining_after_fulfillment)->toBe(28)
        ->and((int) $snap->stocks_needed_for_lead_time)->toBe(20)
        ->and((int) $snap->po_qty)->toBe(40)
        ->and((int) $snap->po_needed)->toBe(32);
});

test('a snapshot row reproduces the same PO QTY and PO Needed the live list shows', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-1',
        'is_active' => true,
        'lead_time' => 5,
        'days_of_coverage' => 7,
        'three_days_average' => 4,
    ]);
    ledger($item, 10);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    // The buffer is a stored column, so the two views can only agree if the
    // snapshot captured it — the flat list and the roll-up alike.
    foreach (['0', '1'] as $summarize) {
        $live = collect(listRows($owner, $workspace, "?summarize=$summarize"))->firstWhere('sku', 'SKU-1');
        $past = collect(listRows($owner, $workspace, "?summarize=$summarize&filter[date]=2026-08-01"))->firstWhere('sku', 'SKU-1');

        // 7 × 4 = 28 buffer, plus 5 × 4 = 20 lead-time demand, less 10 on hand.
        expect((int) $past['po_qty'])->toBe(28)
            ->and((int) $past['po_needed'])->toBe(38)
            ->and((int) $past['po_qty'])->toBe((int) $live['po_qty'])
            ->and((int) $past['po_needed'])->toBe((int) $live['po_needed']);
    }
});

test('re-running the command for a date refreshes rather than duplicates', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 10);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    // Stock moves, then the same date is snapshotted again.
    ledger($item, 99, '2026-06-02');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    $snaps = InventoryItemSnapshot::where('inventory_item_id', $item->id)->get();
    expect($snaps)->toHaveCount(1)
        ->and((int) $snaps->first()->current_stocks)->toBe(99);
});

test('each day keeps its own row so history builds up', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    ledger($item, 4, '2026-06-02');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-02', '--force' => true]);

    $byDate = InventoryItemSnapshot::where('inventory_item_id', $item->id)
        ->pluck('current_stocks', 'snapshot_date');

    expect((int) $byDate['2026-08-01'])->toBe(10)
        ->and((int) $byDate['2026-08-02'])->toBe(4);
});

test('the list reads the newest snapshot, and a date filter reads that day', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    ledger($item, 77, '2026-06-02');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-02', '--force' => true]);

    // Everything the list shows arrives by batch sync, so it reads the newest
    // frozen day rather than recomputing per request.
    $latest = listRows($owner, $workspace, '?summarize=0');
    expect((int) collect($latest)->firstWhere('sku', 'SKU-1')['current_stocks'])->toBe(77);

    $past = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01');
    expect((int) collect($past)->firstWhere('sku', 'SKU-1')['current_stocks'])->toBe(10);
});

test('stock moving after the newest snapshot does not change the list until it runs again', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 10, '2026-06-01');
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    ledger($item, 77, '2026-06-02');

    // The deliberate trade of reading a snapshot: the page is as-of the last
    // run, not as-of now.
    expect((int) collect(listRows($owner, $workspace, '?summarize=0'))->firstWhere('sku', 'SKU-1')['current_stocks'])
        ->toBe(10);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-02', '--force' => true]);

    expect((int) collect(listRows($owner, $workspace, '?summarize=0'))->firstWhere('sku', 'SKU-1')['current_stocks'])
        ->toBe(77);
});

test('a workspace with no snapshot at all still renders, computed live', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    ledger($item, 42, '2026-06-01');

    // Nothing has ever been frozen — falling through to the live query is what
    // keeps a new workspace from looking empty until the first scheduled run.
    expect((int) collect(listRows($owner, $workspace, '?summarize=0'))->firstWhere('sku', 'SKU-1')['current_stocks'])
        ->toBe(42);
});

test('the summarize roll-up works against a snapshot date', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    ledger($a, 30);
    ledger($b, 12);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

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

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

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

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    $searched = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01&filter[search]=ALPHA');
    expect(collect($searched)->pluck('sku')->sort()->values()->all())->toBe(['ALPHA', 'ALPHA-2']);

    $sorted = listRows($owner, $workspace, '?summarize=0&filter[date]=2026-08-01&sort=-current_stocks');
    expect(collect($sorted)->pluck('sku')->first())->toBe('BETA');
});

test('a date with no snapshot shows nothing rather than live data', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 55);

    // Today has been frozen, so the fallback below is not "this workspace is new".
    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=0&filter[date]=2019-01-01')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            // Answering with today's figures under a heading that names 2019
            // would be worse than answering with nothing.
            expect($props['snapshotDate'])->toBeNull()
                ->and($props['requestedDate'])->toBe('2019-01-01')
                ->and($props['items']['data'])->toBeEmpty()
                ->and($props['items']['total'])->toBe(0);
        });
});

test('the list reports which dates have snapshots', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-03', '--force' => true]);

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
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspaceA).'?summarize=0&filter[date]=2026-08-01')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            // A snapshot exists for the date, but not in this workspace.
            expect($page->toArray()['props']['snapshotDate'])->toBeNull();
            expect($page->toArray()['props']['items']['data'])->toBeEmpty();
        });
});

test('the export follows the pinned date and carries every report column', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Widget']);
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'product_id' => $product->id,
        'sku' => 'SKU-1', 'is_active' => true, 'lead_time' => 5, 'three_days_average' => 4,
    ]);

    ledger($item, 10);
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true]);

    // Stock moves after that day was frozen; the pinned export must ignore it.
    ledger($item, 99, '2026-06-02');

    Excel::fake();

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.export', $workspace).'?filter[date]=2026-08-01')
        ->assertOk();

    Excel::assertDownloaded('inventory-items-2026-08-01.xlsx', function (InventoryItemReportExport $export) {
        $rows = iterator_to_array($export->generator());

        // One download carries the whole report, not only the columns the table
        // happened to be showing.
        expect($export->headings())->toHaveCount(32)
            ->and($rows[0][0])->toBe('SKU-1')
            // Current Stocks as frozen on 2026-08-01, not the 99 it is now.
            ->and((int) $rows[0][11])->toBe(10);

        return true;
    });
});

test('the workspace option limits the snapshot to one workspace', function () {
    ['workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();

    InventoryItem::create(['workspace_id' => $workspaceA->id, 'sku' => 'A-1', 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspaceB->id, 'sku' => 'B-1', 'is_active' => true]);

    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--workspace' => $workspaceA->id, '--force' => true]);

    expect(InventoryItemSnapshot::pluck('sku')->all())->toBe(['A-1']);
});

test('editing lead time rewrites today\'s snapshot so the list shows it at once', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'GROUP',
        'is_parent' => true, 'is_active' => true, 'lead_time' => 2,
    ]);
    $child = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'CHILD', 'parent_id' => $parent->id,
        'is_active' => true, 'lead_time' => 2, 'three_days_average' => 3,
    ]);
    ledger($child, 50);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    test()->actingAs($owner)
        ->patch(route('workspaces.inventory.item.lead-time.update', ['workspace' => $workspace, 'item' => $parent->id]), ['lead_time' => 10])
        ->assertRedirect();

    // Frozen against today rather than left for the next scheduled run.
    expect((int) InventoryItemSnapshot::where('inventory_item_id', $parent->id)
        ->where('snapshot_date', now()->toDateString())->value('lead_time'))->toBe(10);

    // And the list, which reads that row, agrees: 10 × the group's 3/day.
    $row = collect(listRows($owner, $workspace, '?summarize=1'))->first();
    expect((int) $row['lead_time'])->toBe(10)
        ->and((int) $row['stocks_needed_for_lead_time'])->toBe(30);

    // The child's row was rewritten too, so the group is not half stale.
    expect((int) InventoryItemSnapshot::where('inventory_item_id', $child->id)
        ->where('snapshot_date', now()->toDateString())->count())->toBe(1);
});

test('an edit never invents a snapshot day that does not already exist', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $kept = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'OTHER', 'is_active' => true]);
    $edited = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'EDITED', 'is_active' => true, 'lead_time' => 1]);
    ledger($kept, 5);
    ledger($edited, 5);

    // Only a past day is frozen; nothing exists for today.
    $this->artisan('inventory:snapshot-items', ['--date' => '2026-08-01', '--force' => true])->assertSuccessful();

    test()->actingAs($owner)
        ->patch(route('workspaces.inventory.item.lead-time.update', ['workspace' => $workspace, 'item' => $edited->id]), ['lead_time' => 9])
        ->assertRedirect();

    // Writing one row for today would make today the newest day, and the list
    // reads the newest day — so a single edit would hide every other item.
    expect(InventoryItemSnapshot::where('snapshot_date', now()->toDateString())->count())->toBe(0)
        ->and(collect(listRows($owner, $workspace, '?summarize=0')))->toHaveCount(2);
});

test('the list reports when the snapshot it is showing was last written', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 10);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    test()->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshotDate', now()->toDateString())
            // The day alone does not say how current the page is: the snapshot
            // is rewritten several times a day and on every edit.
            ->whereNot('snapshotUpdatedAt', null));
});

test('a past date is refused, because the figures would be today\'s', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ledger($item, 42);

    // Nothing here is computed as-of a date — stock comes from the current
    // ledger, waiting stock from the purchase orders as they stand. Writing
    // that under an older date does not record that day, it invents it, and
    // afterwards nothing distinguishes the invented day from a real one.
    $this->artisan('inventory:snapshot-items', ['--date' => now()->subDays(3)->toDateString()])
        ->assertFailed();

    expect(InventoryItemSnapshot::where('workspace_id', $workspace->id)->count())->toBe(0);

    // --force is the way to say that is genuinely what you want.
    $this->artisan('inventory:snapshot-items', ['--date' => now()->subDays(3)->toDateString(), '--force' => true])
        ->assertSuccessful();

    expect((int) InventoryItemSnapshot::where('inventory_item_id', $item->id)->value('current_stocks'))->toBe(42);
});
