<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A sync run of the given type/status, started $daysAgo days ago. */
function syncRun($workspace, string $type, string $status, int $daysAgo = 0): void
{
    DB::table('gencys_sync_runs')->insert([
        'workspace_id' => $workspace->id,
        'inventory_item_id' => null,
        'sync_type' => $type,
        'status' => $status,
        'started_at' => now()->subDays($daysAgo),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** An item to snapshot, so a successful run writes something countable. */
function readyItem($workspace): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'SKU-1', 'is_active' => true,
    ]);
}

function frozenCount($workspace): int
{
    return InventoryItemSnapshot::where('workspace_id', $workspace->id)->count();
}

test('an unfinished sales-tracker run in the last three days holds the snapshot back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // Sales orders are the demand behind every average, and they arrive across
    // several runs a day — a gap two days back still poisons a 3-day average.
    syncRun($workspace, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, GencysSyncRun::STATUS_PENDING, daysAgo: 2);

    $this->artisan('inventory:snapshot-items')->assertFailed();

    expect(frozenCount($workspace))->toBe(0);
})->with([
    GencysSyncRun::STATUS_PENDING,
    GencysSyncRun::STATUS_FAILED,
]);

test('an unfinished transaction or purchase-order run today holds the snapshot back', function (string $type) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    syncRun($workspace, $type, GencysSyncRun::STATUS_FAILED);

    $this->artisan('inventory:snapshot-items')->assertFailed();

    expect(frozenCount($workspace))->toBe(0);
})->with([
    GencysSyncRun::TYPE_TRANSACTION_HISTORY,
    GencysSyncRun::TYPE_PURCHASE_ORDER,
]);

test('yesterday failure of a today-only feed does not hold anything back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // Yesterday's transactions are already in; only today's are still landing.
    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_FAILED, daysAgo: 1);
    // And a sales-tracker gap older than the window it feeds.
    syncRun($workspace, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, GencysSyncRun::STATUS_FAILED, daysAgo: 5);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});

test('runs that finished cleanly, and feeds nobody depends on, do not hold anything back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    syncRun($workspace, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, GencysSyncRun::STATUS_SUCCESS);
    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_SUCCESS);
    syncRun($workspace, GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::STATUS_SUCCESS);
    // Nothing on the items list is built from these two.
    syncRun($workspace, GencysSyncRun::TYPE_INTERN_DAILY_RECORDS, GencysSyncRun::STATUS_FAILED);
    syncRun($workspace, GencysSyncRun::TYPE_PAGE_DETAILS, GencysSyncRun::STATUS_PENDING);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});

test('one workspace waiting on a sync does not stop another being frozen', function () {
    ['workspace' => $blocked] = makeWorkspaceWithOwner();
    ['workspace' => $clear] = makeWorkspaceWithOwner();
    readyItem($blocked);
    readyItem($clear);

    syncRun($blocked, GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::STATUS_PENDING);

    // Something was written, so the run did its job — no failure exit.
    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($blocked))->toBe(0)
        ->and(frozenCount($clear))->toBe(1);
});

test('--ignore-sync freezes anyway, for when the gap is understood', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    syncRun($workspace, GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::STATUS_FAILED);

    $this->artisan('inventory:snapshot-items', ['--ignore-sync' => true])->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});
