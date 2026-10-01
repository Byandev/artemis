<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryItemSnapshot;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A sync run of the given type/status, started $daysAgo days ago. */
function syncRun($workspace, string $type, string $status, int $daysAgo = 0, ?int $batchId = null): void
{
    DB::table('gencys_sync_runs')->insert([
        'workspace_id' => $workspace->id,
        'gencys_sync_batch_id' => $batchId,
        'inventory_item_id' => null,
        'sync_type' => $type,
        'status' => $status,
        'started_at' => now()->subDays($daysAgo),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** A batch in the given state, for runs that belong to one. */
function syncBatch(string $status, array $types): int
{
    return DB::table('gencys_sync_batches')->insertGetId([
        'sync_types' => json_encode($types),
        'status' => $status,
        'source' => 'cron',
        'queued_at' => now(),
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
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
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
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
    readyItem($workspace);

    syncRun($workspace, $type, GencysSyncRun::STATUS_FAILED);

    $this->artisan('inventory:snapshot-items')->assertFailed();

    expect(frozenCount($workspace))->toBe(0);
})->with([
    GencysSyncRun::TYPE_TRANSACTION_HISTORY,
    GencysSyncRun::TYPE_PURCHASE_ORDER,
]);

test('yesterday failure of a today-only feed does not hold anything back', function () {
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
    readyItem($workspace);

    // Yesterday's transactions are already in; only today's are still landing.
    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_FAILED, daysAgo: 1);
    // And a sales-tracker gap older than the window it feeds.
    syncRun($workspace, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, GencysSyncRun::STATUS_FAILED, daysAgo: 5);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});

test('runs that finished cleanly, and feeds nobody depends on, do not hold anything back', function () {
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
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
    ['workspace' => $blocked] = makeGencysWorkspaceWithOwner();
    ['workspace' => $clear] = makeGencysWorkspaceWithOwner();
    readyItem($blocked);
    readyItem($clear);

    syncRun($blocked, GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::STATUS_PENDING);

    // Something was written, so the run did its job — no failure exit.
    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($blocked))->toBe(0)
        ->and(frozenCount($clear))->toBe(1);
});

test('--ignore-sync freezes anyway, for when the gap is understood', function () {
    ['workspace' => $workspace] = makeGencysWorkspaceWithOwner();
    readyItem($workspace);

    syncRun($workspace, GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::STATUS_FAILED);

    $this->artisan('inventory:snapshot-items', ['--ignore-sync' => true])->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});

test('a run still waiting its turn in the batch queue holds the snapshot back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // Batched runs sit `queued` until their group is actually sent — often for
    // hours, since batches drain one at a time. That is data that has not
    // arrived, exactly like `pending`.
    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_QUEUED);

    $this->artisan('inventory:snapshot-items')->assertFailed();

    expect(frozenCount($workspace))->toBe(0);
});

test('a batch that crossed midnight still holds the snapshot back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // The 21:00 pass drains serially and can run past midnight. Its runs carry
    // yesterday's started_at, so the today-only window alone would miss them —
    // but the batch is demonstrably still working.
    $batchId = syncBatch('running', [GencysSyncRun::TYPE_TRANSACTION_HISTORY]);

    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_QUEUED, daysAgo: 1, batchId: $batchId);

    $this->artisan('inventory:snapshot-items')->assertFailed();

    expect(frozenCount($workspace))->toBe(0);
});

test('a finished batch from yesterday does not hold anything back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // Same shape as above, but the batch is done — whatever failed yesterday is
    // yesterday's problem, and today's feed is unaffected.
    $batchId = syncBatch('completed_with_failures', [GencysSyncRun::TYPE_TRANSACTION_HISTORY]);

    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_FAILED, daysAgo: 1, batchId: $batchId);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});

test('a deliberately cancelled run does not hold the snapshot back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    readyItem($workspace);

    // Cancelling is a decision someone made, not a sync that quietly went
    // missing — and --ignore-sync exists for the rest.
    syncRun($workspace, GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::STATUS_CANCELLED);

    $this->artisan('inventory:snapshot-items')->assertSuccessful();

    expect(frozenCount($workspace))->toBe(1);
});
