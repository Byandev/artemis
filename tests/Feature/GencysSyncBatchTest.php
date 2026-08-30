<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\GencysERP\Jobs\SendGencysSyncGroup;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\Inventory\Models\InventoryItem;

/** A workspace wired for ERP automation, plus the raw API key n8n calls back with. */
function makeErpWorkspace(int $items = 0, string $skuPrefix = 'SKU'): array
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill([
        'erp_username' => 'erp-user',
        'erp_password' => 'erp-pass',
    ])->save();

    ['raw' => $raw] = makeApiKey($workspace);

    $created = collect(range(1, max(0, $items)))
        ->when($items < 1, fn () => collect())
        ->map(fn (int $n) => InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => "{$skuPrefix}-{$n}",
            'is_active' => true,
            'is_parent' => false,
        ]));

    return ['workspace' => $workspace, 'raw' => $raw, 'items' => $created];
}

/** Queue a transaction-history-only batch for one date. One run covers the lot. */
function queueTransactionBatch(array $parameters = []): GencysSyncBatch
{
    return app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => array_merge(['dates' => ['08/24/2026']], $parameters)],
    );
}

/**
 * Queue a purchase-order batch — the batch mechanics below need a flow that
 * still opens a run per item, so that a group holds more than one of them.
 */
function queueItemBatch(array $parameters = []): GencysSyncBatch
{
    return app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_PURCHASE_ORDER],
        [GencysSyncRun::TYPE_PURCHASE_ORDER => array_merge(
            ['start_date' => '08/01/2026', 'end_date' => '08/24/2026'],
            $parameters,
        )],
    );
}

/** Report every currently in-flight run back as a success, the way n8n would. */
function reportInFlightRuns(GencysSyncBatch $batch, string $raw, int $rows = 1): void
{
    $runs = $batch->runs()->pending()->get();

    test()->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'data' => $runs->map(fn (GencysSyncRun $run) => [
            'id' => $run->inventory_item_id,
            'sync_run_id' => $run->id,
            'purchased_orders' => collect(range(1, $rows))->map(fn ($n) => [
                'control_no' => "CN-{$run->id}-{$n}",
                'issue_date' => '2026-08-24',
                'total_amount' => 100,
                'status' => 6,
                'items' => [['count' => 1, 'amount' => 100, 'total_amount' => 100]],
                'deliveries' => [],
            ])->all(),
        ])->values()->all(),
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();
}

/** Post one date's transaction report back the way n8n now does: by item name. */
function reportTransactionsForDate(GencysSyncRun $run, string $raw, array $items): void
{
    test()->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run->id,
        'items' => $items,
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();
}

beforeEach(function () {
    // One stub the test can steer — Http::fake() accumulates stubs and the first
    // match wins, so re-faking inside a test would be silently ignored.
    $this->n8nStatus = 200;
    $this->n8nBody = ['ok' => true];
    $this->n8nHeaders = [];

    Http::fake(fn () => Http::response($this->n8nBody, $this->n8nStatus, $this->n8nHeaders));

    config(['gencyserp.batch.group_size' => 2]);
});

test('a batch opens one queued run per item and sends only the first group', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 5);

    Queue::fake();

    $batch = queueItemBatch();

    expect($batch->total_runs)->toBe(5)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->sync_types)->toBe([GencysSyncRun::TYPE_PURCHASE_ORDER]);

    // Exactly one group is in flight; everything else is still waiting its turn.
    expect($batch->runs()->pending()->count())->toBe(2)
        ->and($batch->runs()->queued()->count())->toBe(3);

    Queue::assertPushed(SendGencysSyncGroup::class, 1);
});

test('the next group is only sent once the previous group has reported back', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    $batch = queueItemBatch();
    $firstGroup = $batch->runs()->pending()->pluck('id');

    expect($firstGroup)->toHaveCount(2);

    // Only one of the two comes back — the group isn't done, so nothing new goes out.
    $first = GencysSyncRun::find($firstGroup->first());
    GencysSyncRun::succeedById($workspace->id, $first->id, 1, 1);
    app(BatchRunner::class)->tick();

    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->queued()->count())->toBe(2);

    // The second one lands and the next group goes out.
    GencysSyncRun::succeedById($workspace->id, $firstGroup->last(), 1, 1);
    app(BatchRunner::class)->tick();

    expect($batch->runs()->pending()->count())->toBe(2)
        ->and($batch->runs()->queued()->count())->toBe(0)
        ->and($batch->runs()->pending()->pluck('id')->intersect($firstGroup))->toBeEmpty();
});

test('an n8n callback drives the batch forward on its own', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    $batch = queueItemBatch();

    reportInFlightRuns($batch, $raw);

    // The callback resolved the whole group, so the batch sent the next one
    // without anything else prompting it.
    expect($batch->runs()->pending()->count())->toBe(2)
        ->and($batch->runs()->where('status', GencysSyncRun::STATUS_SUCCESS)->count())->toBe(2);

    reportInFlightRuns($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->succeeded_runs)->toBe(4)
        ->and($batch->failed_runs)->toBe(0)
        ->and($batch->finished_at)->not->toBeNull();
});

test('a run whose callback never arrives is retried on its own, then failed, and the batch carries on', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 3);

    config(['gencyserp.batch.max_retries' => 1]);

    $batch = queueItemBatch();
    $runner = app(BatchRunner::class);

    $stuck = $batch->runs()->pending()->orderBy('id')->first();
    $partner = $batch->runs()->pending()->orderBy('id')->skip(1)->first();

    // Its group-mate answers; the stuck one doesn't.
    GencysSyncRun::succeedById($workspace->id, $partner->id, 1, 1);
    $runner->tick();

    expect($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);

    // Its timeout expires.
    $this->travel(11)->minutes();
    [$retried, $failed] = $runner->expireTimedOutRuns();

    expect($retried)->toBe(1)->and($failed)->toBe(0)
        ->and($stuck->fresh()->attempt)->toBe(1);

    // The retry goes out alone, ahead of the run still queued behind it.
    $runner->tick();

    $inFlight = $batch->runs()->pending()->get();
    expect($inFlight)->toHaveCount(1)
        ->and($inFlight->first()->id)->toBe($stuck->id);

    // It times out again and, with its one retry spent, is failed for good.
    $this->travel(11)->minutes();
    [$retried, $failed] = $runner->expireTimedOutRuns();

    expect($retried)->toBe(0)->and($failed)->toBe(1)
        ->and($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED);

    // The batch moves on to the run that was waiting behind the retry.
    $runner->tick();

    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->queued()->count())->toBe(0);
});

test('a batch ends as completed_with_failures when a run never came back', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 2);

    config(['gencyserp.batch.max_retries' => 0]);

    $batch = queueItemBatch();
    $runner = app(BatchRunner::class);

    $good = $batch->runs()->pending()->orderBy('id')->first();
    GencysSyncRun::succeedById($workspace->id, $good->id, 1, 1);

    $this->travel(11)->minutes();
    $runner->expireTimedOutRuns();
    $runner->tick();

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED_WITH_FAILURES)
        ->and($batch->succeeded_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(1);
});

test('only one batch runs at a time and the next one starts when it finishes', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 2);

    $first = queueItemBatch(['end_date' => '08/23/2026']);
    $second = queueItemBatch(['end_date' => '08/24/2026']);

    expect($first->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_QUEUED)
        ->and($second->runs()->pending()->count())->toBe(0);

    reportInFlightRuns($first, $raw);

    expect($first->fresh()->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($second->runs()->pending()->count())->toBe(2);
});

test('an identical batch that has not started yet is reused instead of duplicated', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $running = queueItemBatch(['end_date' => '08/23/2026']);
    $queued = queueItemBatch(['end_date' => '08/24/2026']);
    $repeat = queueItemBatch(['end_date' => '08/24/2026']);

    expect($repeat->id)->toBe($queued->id)
        ->and($repeat->wasRecentlyCreated)->toBeFalse()
        ->and(GencysSyncBatch::count())->toBe(2)
        ->and($queued->fresh()->total_runs)->toBe(2);

    // A batch already running is never collapsed into — that work is in flight.
    expect($running->id)->not->toBe($queued->id);
});

test('a failed n8n handshake hands the runs back for a retry without re-sending immediately', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $this->n8nStatus = 500;

    $batch = queueItemBatch();

    // The POST failed, so the runs are queued again rather than left in flight.
    expect($batch->runs()->pending()->count())->toBe(0)
        ->and($batch->runs()->queued()->count())->toBe(2)
        ->and($batch->runs()->queued()->first()->attempt)->toBe(1);

    // The sweeper is what picks them back up, which gives n8n room to recover.
    $this->n8nStatus = 200;
    $this->artisan('gencys-erp:sweep-sync-batches')->assertSuccessful();

    expect($batch->runs()->pending()->count())->toBe(1);
});

test('cancelling a batch releases the queue for the one behind it', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    $first = queueItemBatch(['end_date' => '08/23/2026']);
    $second = queueItemBatch(['end_date' => '08/24/2026']);

    app(BatchRunner::class)->cancel($first, 'Cancelled by test');

    $first->refresh();

    expect($first->status)->toBe(GencysSyncBatch::STATUS_CANCELLED)
        ->and($first->cancelled_runs)->toBe(4)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('the purchase-order payload carries the item SKUs and each run id to echo back', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2, skuPrefix: 'ABC');

    queueItemBatch();

    Http::assertSent(function ($request) use ($workspace) {
        $body = $request->data();

        return $body['workspace_id'] === $workspace->id
            && $body['end_date'] === '08/24/2026'
            && $body['erp_username'] === 'erp-user'
            && count($body['items']) === 2
            && collect($body['items'])->pluck('keyword')->all() === ['ABC-1', 'ABC-2']
            && collect($body['items'])->every(fn ($item) => ! empty($item['sync_run_id']))
            && str_contains($body['webhook_url'], '/api/v1/public/purchase-orders/bulk-sync');
    });
});

test('parent and inactive items are never queued', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'PARENT', 'is_active' => true, 'is_parent' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'DORMANT', 'is_active' => false, 'is_parent' => false]);

    $batch = queueItemBatch();

    expect($batch->total_runs)->toBe(2);
});

test('a handshake failure fails the run outright once its retries are spent', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 0]);

    $this->n8nStatus = 500;

    $batch = queueItemBatch();
    $run = $batch->runs()->sole();

    expect($run->status)->toBe(GencysSyncRun::STATUS_FAILED)
        ->and($run->message)->toContain('HTTP 500');

    app(BatchRunner::class)->tick();

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED_WITH_FAILURES)
        ->and($batch->failed_runs)->toBe(1);
});

test('the scheduled sync command queues a single batch covering every sync type', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $this->artisan('gencys-erp:sync', ['--force' => true])->assertSuccessful();

    $batch = GencysSyncBatch::sole();

    expect($batch->sync_types)->toBe([
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
    ])->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);

    // 2 transaction dates + 2 items POs + 3 tracker dates.
    expect($batch->total_runs)->toBe(7)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_TRANSACTION_HISTORY)->count())->toBe(2)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->count())->toBe(2)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_DAILY_SALES_TRACKER)->count())->toBe(3);

    // Each type carries its own window under its own key.
    expect($batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY))->toHaveKey('dates')
        ->and($batch->parametersFor(GencysSyncRun::TYPE_PURCHASE_ORDER))->toHaveKey('start_date')
        ->and($batch->parametersFor(GencysSyncRun::TYPE_DAILY_SALES_TRACKER))->toHaveKey('dates');
});

test('the batch works through its types in order, one group at a time', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 2);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--type' => ['daily_sales_tracker', 'transaction_history'],
    ])->assertSuccessful();

    $batch = GencysSyncBatch::sole();

    expect($batch->sync_types)->toBe([
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
    ]);

    // The tracker goes first, and n8n only takes one date per call, so exactly
    // one run is in flight even though the batch holds plenty more.
    $inFlight = $batch->runs()->pending()->get();

    expect($inFlight)->toHaveCount(1)
        ->and($inFlight->first()->sync_type)->toBe(GencysSyncRun::TYPE_DAILY_SALES_TRACKER);
});

test('an unknown --type is rejected before anything is written', function () {
    makeErpWorkspace(items: 1);

    $this->artisan('gencys-erp:sync', ['--force' => true, '--type' => ['page_details']])
        ->assertFailed();

    expect(GencysSyncBatch::count())->toBe(0);
});

test('a repeat pass collapses into the batches still waiting rather than stacking duplicates', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $this->artisan('gencys-erp:sync', ['--force' => true])->assertSuccessful();
    $running = GencysSyncBatch::sole();

    // The second pass can't collapse into a batch that already started, so it
    // queues a follow-up...
    $this->artisan('gencys-erp:sync', ['--force' => true])->assertSuccessful();
    expect(GencysSyncBatch::count())->toBe(2);

    // ...and a third pass collapses into that follow-up rather than stacking.
    $this->artisan('gencys-erp:sync', ['--force' => true])->assertSuccessful();

    expect(GencysSyncBatch::count())->toBe(2)
        ->and(GencysSyncBatch::queued()->count())->toBe(1)
        ->and(GencysSyncBatch::running()->sole()->id)->toBe($running->id);
});

test('the hand-run trigger command asks for the same window as the scheduled sync', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $this->artisan('gencys-erp:sync', ['--force' => true, '--type' => ['transaction_history']])
        ->assertSuccessful();

    $scheduled = GencysSyncBatch::sole();

    $this->artisan('gencys-erp:trigger-fetch-erp-transaction-history', ['--force' => true])
        ->assertSuccessful();

    $handRun = GencysSyncBatch::orderByDesc('id')->first();

    // Both read their window off the flow, so they ask the ERP for exactly the
    // same thing — which the signature proves. (They're two batches rather than
    // one because the scheduled batch had already started; only a batch still
    // waiting its turn gets collapsed into.)
    expect($handRun->id)->not->toBe($scheduled->id)
        ->and($handRun->sync_types)->toBe($scheduled->sync_types)
        ->and($handRun->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY))
        ->toBe($scheduled->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY))
        ->and($handRun->parameters_signature)->toBe($scheduled->parameters_signature);
});

test('a pass with nothing to sync leaves no empty batch behind', function () {
    // No ERP-connected workspace at all.
    $this->artisan('gencys-erp:sync', ['--force' => true])->assertSuccessful();

    expect(GencysSyncBatch::count())->toBe(0)
        ->and(GencysSyncRun::count())->toBe(0);
});

test('the batch tallies keep up as its groups report back, not only at the end', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    $batch = queueItemBatch();

    expect($batch->succeeded_runs)->toBe(0)
        ->and($batch->progressPercent())->toBe(0);

    // First group of two comes back — the batch is still running, but its
    // counters (which are what the UI draws progress from) must already say so.
    reportInFlightRuns($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->total_runs)->toBe(4)
        ->and($batch->succeeded_runs)->toBe(2)
        ->and($batch->progressPercent())->toBe(50);

    reportInFlightRuns($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->succeeded_runs)->toBe(4)
        ->and($batch->progressPercent())->toBe(100);
});

test('a timed-out run shows up in the tallies while the batch is still going', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    config(['gencyserp.batch.max_retries' => 0]);

    $batch = queueItemBatch();
    $runner = app(BatchRunner::class);

    $good = $batch->runs()->pending()->orderBy('id')->first();
    GencysSyncRun::succeedById($workspace->id, $good->id, 1, 1);

    $this->travel(11)->minutes();
    $runner->expireTimedOutRuns();
    $runner->tick();

    $batch->refresh();

    // Two runs resolved (one ok, one failed) out of four, and the batch has
    // moved on to its next group rather than sitting at zero.
    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->succeeded_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(1)
        ->and($batch->progressPercent())->toBe(50);
});

test('the execution id lands on inventory runs too, and clears on retry', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 2);

    config(['gencyserp.batch.max_retries' => 1]);

    $batch = queueItemBatch();
    $runs = $batch->runs()->pending()->orderBy('id')->get();

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        // One execution handled the whole group, so it rides at the top level.
        'n8n_execution_id' => '77123',
        'data' => [[
            'id' => $runs->first()->inventory_item_id,
            'sync_run_id' => $runs->first()->id,
            'purchased_orders' => [[
                'control_no' => 'CN-1',
                'issue_date' => '2026-08-24',
                'total_amount' => 100,
                'status' => 6,
                'items' => [['count' => 1, 'amount' => 100, 'total_amount' => 100]],
            ]],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($runs->first()->fresh()->n8n_execution_id)->toBe('77123');

    // The other run times out. Its next attempt will be a different execution,
    // so the stale id must not follow it into the retry.
    $stuck = $runs->last();
    $stuck->forceFill(['n8n_execution_id' => '77123'])->save();

    $this->travel(11)->minutes();
    app(BatchRunner::class)->expireTimedOutRuns();

    $stuck->refresh();

    expect($stuck->status)->toBe(GencysSyncRun::STATUS_QUEUED)
        ->and($stuck->attempt)->toBe(1)
        ->and($stuck->n8n_execution_id)->toBeNull();
});

test('the execution id n8n answers with is stamped on the group it took', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    // n8n reports the execution it started in the response to our call.
    $this->n8nBody = ['executionId' => '88231'];

    $batch = queueItemBatch();

    expect($batch->runs()->pluck('n8n_execution_id')->unique()->all())->toBe(['88231']);
});

test('the execution id is found inside a wrapped response', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $this->n8nBody = ['data' => ['execution_id' => '4410']];

    expect(queueItemBatch()->runs()->sole()->n8n_execution_id)->toBe('4410');
});

test('the execution id is found in a response header', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    // n8n's default body says nothing useful, but the header carries it.
    $this->n8nBody = ['message' => 'Workflow was started'];
    $this->n8nHeaders = ['x-n8n-execution-id' => '4411'];

    expect(queueItemBatch()->runs()->sole()->n8n_execution_id)->toBe('4411');
});

test('a response with no execution id leaves the runs unstamped rather than failing', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $this->n8nBody = ['message' => 'Workflow was started'];

    $batch = queueItemBatch();

    expect($batch->runs()->pending()->count())->toBe(2)
        ->and($batch->runs()->whereNotNull('n8n_execution_id')->count())->toBe(0);
});

test('a transaction-history batch opens one run per date, whatever the item count', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 5);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/23/2026', '08/24/2026']]],
    );

    // A date is the subject now, so the five items make no difference: two dates,
    // two runs, and neither of them belongs to an item.
    expect($batch->total_runs)->toBe(2)
        ->and($batch->runs()->whereNotNull('inventory_item_id')->count())->toBe(0)
        ->and($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->queued()->count())->toBe(1);
});

test('a workspace with no inventory items still syncs the date', function () {
    // Nothing to enumerate any more — whatever is on the report comes back, and
    // the callback creates the items it needs.
    makeErpWorkspace(items: 0);

    expect(queueTransactionBatch()->total_runs)->toBe(1);
});

test('the transaction-history payload asks for a date rather than a list of items', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $run = queueTransactionBatch()->runs()->pending()->sole();

    Http::assertSent(function ($request) use ($workspace, $run) {
        $body = $request->data();

        return $body['workspace_id'] === $workspace->id
            && $body['date'] === '08/24/2026'
            && $body['erp_username'] === 'erp-user'
            && $body['sync_run_id'] === $run->id
            && ! array_key_exists('items', $body)
            && str_contains($body['webhook_url'], '/api/v1/public/inventory-items/transactions/bulk-sync');
    });
});

test('one bulk transaction callback closes the date and releases the next one', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1, skuPrefix: 'Airzen');

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/23/2026', '08/24/2026']]],
    );

    $first = $batch->runs()->pending()->sole();

    reportTransactionsForDate($first, $raw, [
        ['item' => 'Airzen-1', 'transactions' => [
            ['number' => 1, 'date' => '2026-08-23', 'ref_no' => 'Rigor Esperanzate', 'po_qty_out' => '7', 'inventory_remaining_stock' => '1,249'],
        ]],
        ['item' => 'Amazing Kidney Care Patch', 'transactions' => [
            ['number' => 1, 'date' => '2026-08-23', 'ref_no' => 'Anna Marie Mallo', 'po_qty_in' => '2,400', 'inventory_remaining_stock' => '2,400'],
        ]],
    ]);

    $first->refresh();

    // The one run covers every item on the report, and finishing it lets the
    // second date go out.
    expect($first->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($first->rows_received)->toBe(2)
        ->and($first->rows_saved)->toBe(2)
        ->and($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->pending()->sole()->id)->not->toBe($first->id);
});
