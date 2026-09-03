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

/**
 * Queue a transaction-history batch. Every flow now syncs a whole window in one
 * run, so a batch holds several runs by covering several dates — which is how
 * the tests below get a queue worth stepping through.
 */
function queueTransactionBatch(array $dates = ['08/24/2026']): GencysSyncBatch
{
    return app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => $dates]],
    );
}

/** Report the run currently in flight back as a success, the way n8n would. */
function reportInFlightRun(GencysSyncBatch $batch, string $raw, int $rows = 1): void
{
    $run = $batch->runs()->pending()->orderBy('id')->first();

    test()->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run?->id,
        'items' => [[
            'item' => 'SKU-1',
            'transactions' => collect(range(1, $rows))->map(fn ($n) => [
                'number' => $n,
                'ref_no' => "TX-{$run?->id}-{$n}",
                'date' => '2026-08-24',
                'po_qty_in' => 1,
                'inventory_remaining_stock' => 1,
            ])->all(),
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();
}

beforeEach(function () {
    // One stub the test can steer — Http::fake() accumulates stubs and the first
    // match wins, so re-faking inside a test would be silently ignored.
    $this->n8nStatus = 200;
    $this->n8nBody = ['ok' => true];
    $this->n8nHeaders = [];

    Http::fake(fn () => Http::response($this->n8nBody, $this->n8nStatus, $this->n8nHeaders));
});

test('a batch opens one queued run per date and sends only the first', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 5);

    Queue::fake();

    $batch = queueTransactionBatch(['08/22/2026', '08/23/2026', '08/24/2026']);

    // The five items make no difference: a date is the subject, so three dates
    // are three runs, and none of them belongs to an item.
    expect($batch->total_runs)->toBe(3)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->sync_types)->toBe([GencysSyncRun::TYPE_TRANSACTION_HISTORY])
        ->and($batch->runs()->whereNotNull('inventory_item_id')->count())->toBe(0);

    // Exactly one run is in flight; the rest are still waiting their turn.
    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->queued()->count())->toBe(2);

    Queue::assertPushed(SendGencysSyncGroup::class, 1);
});

test('the next run is only sent once the one in flight has reported back', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    $batch = queueTransactionBatch(['08/23/2026', '08/24/2026']);
    $first = $batch->runs()->pending()->sole();

    // Ticking while a run is still out changes nothing — the ERP is busy.
    app(BatchRunner::class)->tick();

    expect($batch->runs()->pending()->pluck('id')->all())->toBe([$first->id])
        ->and($batch->runs()->queued()->count())->toBe(1);

    // It lands, and the next date goes out.
    GencysSyncRun::succeedById($workspace->id, $first->id, 1, 1);
    app(BatchRunner::class)->tick();

    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->pending()->sole()->id)->not->toBe($first->id)
        ->and($batch->runs()->queued()->count())->toBe(0);
});

test('an n8n callback drives the batch forward on its own', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    $batch = queueTransactionBatch(['08/23/2026', '08/24/2026']);

    reportInFlightRun($batch, $raw);

    // The callback resolved the run, so the batch sent the next one without
    // anything else prompting it.
    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->where('status', GencysSyncRun::STATUS_SUCCESS)->count())->toBe(1);

    reportInFlightRun($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->succeeded_runs)->toBe(2)
        ->and($batch->failed_runs)->toBe(0)
        ->and($batch->finished_at)->not->toBeNull();
});

test('a run whose callback never arrives is retried, then failed, and the batch carries on', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 1]);

    $batch = queueTransactionBatch(['08/23/2026', '08/24/2026']);
    $runner = app(BatchRunner::class);

    $stuck = $batch->runs()->pending()->sole();

    // Its timeout expires with nothing having come back.
    $this->travel(11)->minutes();
    [$retried, $failed] = $runner->expireTimedOutRuns();

    expect($retried)->toBe(1)->and($failed)->toBe(0)
        ->and($stuck->fresh()->attempt)->toBe(1);

    // The retry goes out ahead of the date still queued behind it.
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
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 0]);

    $batch = queueTransactionBatch(['08/23/2026', '08/24/2026']);
    $runner = app(BatchRunner::class);

    $good = $batch->runs()->pending()->sole();
    GencysSyncRun::succeedById($workspace->id, $good->id, 1, 1);
    $runner->tick();

    // The second date goes out and is never answered.
    $this->travel(11)->minutes();
    $runner->expireTimedOutRuns();
    $runner->tick();

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED_WITH_FAILURES)
        ->and($batch->succeeded_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(1);
});

test('only one batch runs at a time and the next one starts when it finishes', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    $first = queueTransactionBatch(['08/23/2026']);
    $second = queueTransactionBatch(['08/24/2026']);

    expect($first->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_QUEUED)
        ->and($second->runs()->pending()->count())->toBe(0);

    reportInFlightRun($first, $raw);

    expect($first->fresh()->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($second->runs()->pending()->count())->toBe(1);
});

test('an identical batch that has not started yet is reused instead of duplicated', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $running = queueTransactionBatch(['08/23/2026']);
    $queued = queueTransactionBatch(['08/24/2026']);
    $repeat = queueTransactionBatch(['08/24/2026']);

    expect($repeat->id)->toBe($queued->id)
        ->and($repeat->wasRecentlyCreated)->toBeFalse()
        ->and(GencysSyncBatch::count())->toBe(2)
        ->and($queued->fresh()->total_runs)->toBe(1);

    // A batch already running is never collapsed into — that work is in flight.
    expect($running->id)->not->toBe($queued->id);
});

test('a failed n8n handshake hands the run back for a retry without re-sending immediately', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $this->n8nStatus = 500;

    $batch = queueTransactionBatch();

    // The POST failed, so the run is queued again rather than left in flight.
    expect($batch->runs()->pending()->count())->toBe(0)
        ->and($batch->runs()->queued()->count())->toBe(1)
        ->and($batch->runs()->queued()->first()->attempt)->toBe(1);

    // The sweeper is what picks it back up, which gives n8n room to recover.
    $this->n8nStatus = 200;
    $this->artisan('gencys-erp:sweep-sync-batches')->assertSuccessful();

    expect($batch->runs()->pending()->count())->toBe(1);
});

test('cancelling a batch releases the queue for the one behind it', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    $first = queueTransactionBatch(['08/21/2026', '08/22/2026', '08/23/2026']);
    $second = queueTransactionBatch(['08/24/2026']);

    app(BatchRunner::class)->cancel($first, 'Cancelled by test');

    $first->refresh();

    expect($first->status)->toBe(GencysSyncBatch::STATUS_CANCELLED)
        ->and($first->cancelled_runs)->toBe(3)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
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

test('the purchase-order payload asks for a range rather than a list of items', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_PURCHASE_ORDER],
        [GencysSyncRun::TYPE_PURCHASE_ORDER => ['start_date' => '08/01/2026', 'end_date' => '08/24/2026']],
    );

    // One workspace, one range, one run — the two items are irrelevant.
    $run = $batch->runs()->pending()->sole();

    expect($batch->total_runs)->toBe(1)
        ->and($run->inventory_item_id)->toBeNull();

    Http::assertSent(function ($request) use ($workspace, $run) {
        $body = $request->data();

        return $body['workspace_id'] === $workspace->id
            && $body['start_date'] === '08/01/2026'
            && $body['end_date'] === '08/24/2026'
            && $body['sync_run_id'] === $run->id
            && ! array_key_exists('items', $body)
            // The delivered list is workspace-wide, so it survives the change.
            && array_key_exists('delivered_purchase_orders_no', $body)
            && str_contains($body['webhook_url'], '/api/v1/public/purchase-orders/bulk-sync');
    });
});

test('a workspace with no inventory items still syncs its window', function () {
    // Nothing to enumerate any more — whatever is on the report comes back, and
    // the callback creates the items it needs.
    makeErpWorkspace(items: 0);

    expect(queueTransactionBatch()->total_runs)->toBe(1);
});

test('a handshake failure fails the run outright once its retries are spent', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 0]);

    $this->n8nStatus = 500;

    $batch = queueTransactionBatch();
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
        GencysSyncRun::TYPE_INTERN_DAILY_RECORDS,
    ])->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);

    // 2 transaction dates + 1 PO range + 3 tracker dates. No intern daily
    // records: this workspace has no synced interns to ask about yet.
    expect($batch->total_runs)->toBe(6)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_TRANSACTION_HISTORY)->count())->toBe(2)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->count())->toBe(1)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_DAILY_SALES_TRACKER)->count())->toBe(3)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_INTERN_DAILY_RECORDS)->count())->toBe(0);

    // Each type carries its own window under its own key.
    expect($batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY))->toHaveKey('dates')
        ->and($batch->parametersFor(GencysSyncRun::TYPE_PURCHASE_ORDER))->toHaveKey('start_date')
        ->and($batch->parametersFor(GencysSyncRun::TYPE_DAILY_SALES_TRACKER))->toHaveKey('dates');
});

test('the batch works through its types in order, one run at a time', function () {
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

    // The tracker goes first, and every flow takes one window per call, so
    // exactly one run is in flight even though the batch holds plenty more.
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

test('the batch tallies keep up as its runs report back, not only at the end', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    $batch = queueTransactionBatch(['08/21/2026', '08/22/2026', '08/23/2026', '08/24/2026']);

    expect($batch->succeeded_runs)->toBe(0)
        ->and($batch->progressPercent())->toBe(0);

    // The first two come back — the batch is still running, but its counters
    // (which are what the UI draws progress from) must already say so.
    reportInFlightRun($batch, $raw);
    reportInFlightRun($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->total_runs)->toBe(4)
        ->and($batch->succeeded_runs)->toBe(2)
        ->and($batch->progressPercent())->toBe(50);

    reportInFlightRun($batch, $raw);
    reportInFlightRun($batch, $raw);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->succeeded_runs)->toBe(4)
        ->and($batch->progressPercent())->toBe(100);
});

test('a timed-out run shows up in the tallies while the batch is still going', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 0]);

    $batch = queueTransactionBatch(['08/21/2026', '08/22/2026', '08/23/2026', '08/24/2026']);
    $runner = app(BatchRunner::class);

    reportInFlightRun($batch, $raw);

    // The next one is in flight and never answers.
    $this->travel(11)->minutes();
    $runner->expireTimedOutRuns();
    $runner->tick();

    $batch->refresh();

    // Two runs resolved (one ok, one failed) out of four, and the batch has
    // moved on to the next date rather than sitting at zero.
    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->succeeded_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(1)
        ->and($batch->progressPercent())->toBe(50);
});

test('the execution id lands on the run a callback answers, and clears on retry', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 1);

    config(['gencyserp.batch.max_retries' => 1]);

    $batch = queueTransactionBatch(['08/23/2026', '08/24/2026']);
    $first = $batch->runs()->pending()->sole();

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        // One execution handled the whole date, so it rides at the top level.
        'n8n_execution_id' => '77123',
        'sync_run_id' => $first->id,
        'items' => [[
            'item' => 'SKU-1',
            'transactions' => [['number' => 1, 'ref_no' => 'TX-1', 'date' => '2026-08-23', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1]],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($first->fresh()->n8n_execution_id)->toBe('77123');

    // The second date times out. Its next attempt will be a different
    // execution, so the stale id must not follow it into the retry.
    $stuck = $batch->runs()->pending()->sole();
    $stuck->forceFill(['n8n_execution_id' => '77123'])->save();

    $this->travel(11)->minutes();
    app(BatchRunner::class)->expireTimedOutRuns();

    $stuck->refresh();

    expect($stuck->status)->toBe(GencysSyncRun::STATUS_QUEUED)
        ->and($stuck->attempt)->toBe(1)
        ->and($stuck->n8n_execution_id)->toBeNull();
});

test('the execution id n8n answers with is stamped on the run it took', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    // n8n reports the execution it started in the response to our call.
    $this->n8nBody = ['executionId' => '88231'];

    $batch = queueTransactionBatch();

    expect($batch->runs()->sole()->n8n_execution_id)->toBe('88231');
});

test('the execution id is found inside a wrapped response', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $this->n8nBody = ['data' => ['execution_id' => '4410']];

    expect(queueTransactionBatch()->runs()->sole()->n8n_execution_id)->toBe('4410');
});

test('the execution id is found in a response header', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    // n8n's default body says nothing useful, but the header carries it.
    $this->n8nBody = ['message' => 'Workflow was started'];
    $this->n8nHeaders = ['x-n8n-execution-id' => '4411'];

    expect(queueTransactionBatch()->runs()->sole()->n8n_execution_id)->toBe('4411');
});

test('a response with no execution id leaves the run unstamped rather than failing', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 1);

    $this->n8nBody = ['message' => 'Workflow was started'];

    $batch = queueTransactionBatch();

    expect($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->whereNotNull('n8n_execution_id')->count())->toBe(0);
});
