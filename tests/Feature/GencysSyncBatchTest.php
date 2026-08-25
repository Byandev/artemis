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

/** Queue a transaction-history-only batch for one date. */
function queueTransactionBatch(array $parameters = []): GencysSyncBatch
{
    return app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => array_merge(['dates' => ['08/24/2026']], $parameters)],
    );
}

/** Report every currently in-flight run back as a success, the way n8n would. */
function reportInFlightRuns(GencysSyncBatch $batch, string $raw, int $rows = 1): void
{
    $runs = $batch->runs()->pending()->get();

    test()->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => $runs->map(fn (GencysSyncRun $run) => [
            'id' => $run->inventory_item_id,
            'sync_run_id' => $run->id,
            'transactions' => collect(range(1, $rows))->map(fn ($n) => [
                'ref_no' => "TX-{$run->id}-{$n}",
                'date' => '2026-08-24',
                'po_qty_in' => 1,
                'inventory_remaining_stock' => 1,
            ])->all(),
        ])->values()->all(),
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();
}

beforeEach(function () {
    // One stub whose status the test can move — Http::fake() accumulates stubs
    // and the first match wins, so re-faking inside a test would be ignored.
    $this->n8nStatus = 200;
    Http::fake(fn () => Http::response(['ok' => true], $this->n8nStatus));

    config(['gencyserp.batch.group_size' => 2]);
});

test('a batch opens one queued run per item per date and sends only the first group', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 5);

    Queue::fake();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/23/2026', '08/24/2026']]],
    );

    expect($batch->total_runs)->toBe(10)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->sync_types)->toBe([GencysSyncRun::TYPE_TRANSACTION_HISTORY]);

    // Exactly one group is in flight; everything else is still waiting its turn.
    expect($batch->runs()->pending()->count())->toBe(2)
        ->and($batch->runs()->queued()->count())->toBe(8);

    Queue::assertPushed(SendGencysSyncGroup::class, 1);
});

test('the next group is only sent once the previous group has reported back', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeErpWorkspace(items: 4);

    $batch = queueTransactionBatch();
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

    $batch = queueTransactionBatch();

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

    $batch = queueTransactionBatch();
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

    $batch = queueTransactionBatch();
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

    $first = queueTransactionBatch(['dates' => ['08/23/2026']]);
    $second = queueTransactionBatch(['dates' => ['08/24/2026']]);

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

    $running = queueTransactionBatch(['dates' => ['08/23/2026']]);
    $queued = queueTransactionBatch(['dates' => ['08/24/2026']]);
    $repeat = queueTransactionBatch(['dates' => ['08/24/2026']]);

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

    $batch = queueTransactionBatch();

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

    $first = queueTransactionBatch(['dates' => ['08/23/2026']]);
    $second = queueTransactionBatch(['dates' => ['08/24/2026']]);

    app(BatchRunner::class)->cancel($first, 'Cancelled by test');

    $first->refresh();

    expect($first->status)->toBe(GencysSyncBatch::STATUS_CANCELLED)
        ->and($first->cancelled_runs)->toBe(4)
        ->and($second->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('the payload n8n receives carries the item SKUs and each run id to echo back', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2, skuPrefix: 'ABC');

    queueTransactionBatch();

    Http::assertSent(function ($request) use ($workspace) {
        $body = $request->data();

        return $body['workspace_id'] === $workspace->id
            && $body['date'] === '08/24/2026'
            && $body['erp_username'] === 'erp-user'
            && count($body['items']) === 2
            && collect($body['items'])->pluck('keyword')->all() === ['ABC-1', 'ABC-2']
            && collect($body['items'])->every(fn ($item) => ! empty($item['sync_run_id']))
            && str_contains($body['webhook_url'], '/api/v1/public/inventory-items/transactions/bulk-sync');
    });
});

test('parent and inactive items are never queued', function () {
    ['workspace' => $workspace] = makeErpWorkspace(items: 2);

    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'PARENT', 'is_active' => true, 'is_parent' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'DORMANT', 'is_active' => false, 'is_parent' => false]);

    $batch = queueTransactionBatch();

    expect($batch->total_runs)->toBe(2);
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
    ])->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);

    // 2 items x 2 dates transactions + 2 items POs + 3 tracker dates.
    expect($batch->total_runs)->toBe(9)
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_TRANSACTION_HISTORY)->count())->toBe(4)
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
