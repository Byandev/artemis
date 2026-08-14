<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Actions\RetryGencysSyncRuns;
use Modules\GencysERP\Jobs\FetchDailySalesTrackerJob;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;

/** A workspace wired for ERP automation: credentials plus an API key for callbacks. */
function makeErpWorkspace()
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    makeApiKey($workspace);
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);

    return $workspace;
}

/** A batch with one pending run, which is what blocks the next slot. */
function makeRunningBatch($workspace): GencysSyncBatch
{
    $batch = GencysSyncBatch::start($workspace->id);

    GencysSyncRun::start(
        $workspace->id,
        null,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        ['date' => '2026-08-14'],
        $batch->id,
    );

    return $batch->refreshCounters();
}

test('a batch stays running while any run is still pending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = makeRunningBatch($workspace);

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->total_runs)->toBe(1)
        ->and($batch->finished_at)->toBeNull();
});

test('a batch completes when its last run succeeds', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = makeRunningBatch($workspace);
    $run = $batch->runs()->first();

    GencysSyncRun::succeedById($workspace->id, $run->id, 12, 12);

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->completed_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(0)
        ->and($batch->finished_at)->not->toBeNull();
});

test('a batch with a mix of outcomes lands on partial', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = GencysSyncBatch::start($workspace->id);

    $ok = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '2026-08-14'], $batch->id);
    $bad = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '2026-08-13'], $batch->id);

    GencysSyncRun::succeedById($workspace->id, $ok->id, 5, 5);
    $bad->fail('n8n webhook unreachable');

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_PARTIAL)
        ->and($batch->completed_runs)->toBe(1)
        ->and($batch->failed_runs)->toBe(1);
});

test('a batch that opened no runs completes instead of hanging', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // The orchestrator opens the batch before its child commands run. If none of
    // them find eligible work the batch must still close, or the guard would
    // block this workspace at every future slot.
    $batch = GencysSyncBatch::start($workspace->id)->refreshCounters();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->total_runs)->toBe(0);
});

test('openFor only reports a batch that is still running', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = makeRunningBatch($workspace);

    expect(GencysSyncBatch::openFor($workspace->id)?->id)->toBe($batch->id);

    GencysSyncRun::succeedById($workspace->id, $batch->runs()->first()->id, 1, 1);

    // Completed, partial and failed batches are all finished — the next slot
    // should go ahead rather than stall behind one that will never change.
    expect(GencysSyncBatch::openFor($workspace->id))->toBeNull();
});

test('the guard blocks a workspace whose previous batch is still running', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $open = makeRunningBatch($workspace);
    $skipped = GencysSyncBatch::skip($workspace->id, $open);

    expect($skipped->status)->toBe(GencysSyncBatch::STATUS_SKIPPED)
        ->and($skipped->message)->toContain("batch #{$open->id}")
        ->and($skipped->meta['blocked_by_batch_id'])->toBe($open->id);
});

test('a skipped batch is never revived by a recount', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $open = makeRunningBatch($workspace);
    $skipped = GencysSyncBatch::skip($workspace->id, $open)->refreshCounters();

    expect($skipped->status)->toBe(GencysSyncBatch::STATUS_SKIPPED);
});

test('the guard blocks per workspace, not globally', function () {
    ['workspace' => $blocked] = makeWorkspaceWithOwner();
    ['workspace' => $free] = makeWorkspaceWithOwner();

    makeRunningBatch($blocked);

    expect(GencysSyncBatch::openFor($blocked->id))->not->toBeNull()
        ->and(GencysSyncBatch::openFor($free->id))->toBeNull();
});

test('resolving an earlier run closes its batch so the guard unsticks', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // The 09:30 sweep never got its callback and is still holding the workspace.
    $stale = GencysSyncBatch::start($workspace->id);
    $staleRun = GencysSyncRun::start(
        $workspace->id,
        null,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        ['date' => '2026-08-14'],
        $stale->id,
    );
    $stale->refreshCounters();

    expect(GencysSyncBatch::openFor($workspace->id)?->id)->toBe($stale->id);

    // A later run for the same date comes back. resolveEarlierRunsWithSameParameters
    // silently closes the stale run — its batch has to be recounted too, or this
    // workspace would be blocked forever.
    $later = GencysSyncRun::start(
        $workspace->id,
        null,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        ['date' => '2026-08-14'],
    );

    GencysSyncRun::succeedById($workspace->id, $later->id, 8, 8);

    $staleRun->refresh();
    $stale->refresh();

    expect($staleRun->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stale->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and(GencysSyncBatch::openFor($workspace->id))->toBeNull();
});

test('the stale sweeper fails outstanding runs and closes their batch', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = makeRunningBatch($workspace);
    $batch->runs()->update(['started_at' => now()->subHours(5)]);
    // The reconcile pass ignores batches younger than 15 minutes to avoid racing
    // the orchestrator, so age this one past that floor.
    $batch->forceFill(['started_at' => now()->subHours(5)])->save();

    $this->artisan('gencys-erp:expire-stale-sync-runs', ['--hours' => 3])->assertSuccessful();

    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_FAILED)
        ->and($batch->failed_runs)->toBe(1)
        ->and(GencysSyncBatch::openFor($workspace->id))->toBeNull();
});

test('a retry reopens a finished batch', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $batch = makeRunningBatch($workspace);
    $batch->runs()->first()->fail('n8n webhook unreachable');
    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_FAILED)
        ->and($batch->finished_at)->not->toBeNull();

    // A retry attaches a fresh pending run to the same batch.
    GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '2026-08-14'], $batch->id);
    $batch->refreshCounters();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->finished_at)->toBeNull()
        ->and($batch->total_runs)->toBe(2);
});

test('the orchestrator opens one batch per workspace and attaches its runs', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys']);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--sync' => true,
        '--type' => ['daily_sales_tracker'],
    ])->assertSuccessful();

    $batch = GencysSyncBatch::where('workspace_id', $workspace->id)->sole();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->total_runs)->toBeGreaterThan(0)
        // Every run the child command opened is tied to this batch.
        ->and($batch->runs()->count())->toBe($batch->total_runs)
        ->and(GencysSyncRun::whereNull('batch_id')->count())->toBe(0);
});

test('the orchestrator skips a workspace whose previous batch is still running', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys']);

    $open = makeRunningBatch($workspace);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--sync' => true,
        '--type' => ['daily_sales_tracker'],
    ])->assertSuccessful();

    $skipped = GencysSyncBatch::where('workspace_id', $workspace->id)
        ->where('status', GencysSyncBatch::STATUS_SKIPPED)
        ->sole();

    expect($skipped->status)->toBe(GencysSyncBatch::STATUS_SKIPPED)
        ->and($skipped->meta['blocked_by_batch_id'])->toBe($open->id)
        // Nothing was dispatched: the only runs are the blocking batch's own.
        ->and(GencysSyncRun::count())->toBe(1);

    // No webhook went out for the skipped slot.
    Http::assertNothingSent();
});

test('--ignore-guard dispatches even when a batch is still running', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys']);

    makeRunningBatch($workspace);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--sync' => true,
        '--ignore-guard' => true,
        '--type' => ['daily_sales_tracker'],
    ])->assertSuccessful();

    $batch = GencysSyncBatch::where('workspace_id', $workspace->id)
        ->latest('id')
        ->first();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING)
        ->and($batch->total_runs)->toBeGreaterThan(0);
});

test('one workspace being blocked does not stop the others dispatching', function () {
    $blocked = makeErpWorkspace();
    $free = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys']);

    makeRunningBatch($blocked);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--sync' => true,
        '--type' => ['daily_sales_tracker'],
    ])->assertSuccessful();

    expect(GencysSyncBatch::where('workspace_id', $blocked->id)->latest('id')->first()->status)
        ->toBe(GencysSyncBatch::STATUS_SKIPPED)
        ->and(GencysSyncBatch::where('workspace_id', $free->id)->latest('id')->first()->status)
        ->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('retrying a batch re-dispatches only its failed runs', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys']);

    $batch = GencysSyncBatch::start($workspace->id);

    $ok = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/13/2026'], $batch->id);
    $bad = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);

    GencysSyncRun::succeedById($workspace->id, $ok->id, 5, 5);
    $bad->fail('n8n webhook unreachable');
    $batch->refresh();

    expect($batch->status)->toBe(GencysSyncBatch::STATUS_PARTIAL);

    $replayed = app(RetryGencysSyncRuns::class)->forBatch($batch->fresh());

    $batch->refresh();

    // Only the failed run was replayed, and it reopened the batch.
    expect($replayed)->toBe(1)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);

    // The replay asked for the failed run's date, not the successful one's.
    Http::assertSent(fn ($request) => $request['date'] === '08/14/2026');
    Http::assertNotSent(fn ($request) => $request['date'] === '08/13/2026');
});

test('a successful retry converges the batch back to completed', function () {
    $workspace = makeErpWorkspace();

    $batch = GencysSyncBatch::start($workspace->id);
    $failed = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);
    $failed->fail('n8n webhook unreachable');

    // The retry opens a new run for the same parameters.
    $retryRun = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);
    $batch->refreshCounters();

    GencysSyncRun::succeedById($workspace->id, $retryRun->id, 9, 9);

    $batch->refresh();
    $failed->refresh();

    // The original failure is resolved by the retry that fetched the same data,
    // so the batch ends up clean rather than stuck on partial forever.
    expect($failed->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_COMPLETED)
        ->and($batch->failed_runs)->toBe(0);
});

test('retrying a purchase-order run replays its original date range', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.purchase_order_webhook_url' => 'https://n8n.test/webhook/gencys']);

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-RETRY',
        'is_active' => true,
    ]);

    $batch = GencysSyncBatch::start($workspace->id);
    $run = GencysSyncRun::start(
        $workspace->id,
        $item->id,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
        ['start_date' => '05/14/2026', 'end_date' => '08/14/2026'],
        $batch->id,
    );
    $run->fail('timed out');

    app(RetryGencysSyncRuns::class)->forRun($run->fresh());

    // meta stores ERP-style m/d/Y; the command takes Y-m-d. If that conversion
    // is wrong the retry silently fetches the wrong range.
    Http::assertSent(fn ($request) => $request['start_date'] === '05/14/2026'
        && $request['end_date'] === '08/14/2026');
});

test('the retry endpoint rejects a batch from another workspace', function () {
    ['workspace' => $mine, 'user' => $user] = makeWorkspaceWithOwner();
    $theirs = makeErpWorkspace();

    $batch = GencysSyncBatch::start($theirs->id);

    $this->actingAs($user)
        ->post("/workspaces/{$mine->id}/inventory/sync-health/batches/{$batch->id}/retry")
        ->assertNotFound();
});

test('the transaction-history callback stores the n8n execution id', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-EXEC',
        'is_active' => true,
    ]);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'sync_run_id' => $run->id,
            'n8n_execution_id' => 48211,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-08-14', 'po_qty_in' => 3, 'inventory_remaining_stock' => 3],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->n8n_execution_id)->toBe(48211);
});

test('the daily sales callback stores the n8n execution id', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026']);

    $this->postJson('/api/v1/public/gencys/daily-sales-tracker', [
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'execution_id' => 90210,
        'orders' => [],
    ])->assertOk();

    // Key spelling varies between flows, so the extractor accepts execution_id too.
    expect($run->fresh()->n8n_execution_id)->toBe(90210);
});

test('a callback without an execution id leaves an existing one intact', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026']);
    $run->forceFill(['n8n_execution_id' => 777])->save();

    $this->postJson('/api/v1/public/gencys/daily-sales-tracker', [
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'orders' => [],
    ])->assertOk();

    expect($run->fresh()->n8n_execution_id)->toBe(777);
});

test('a run with an execution id is replayed through the n8n API, not re-scraped', function () {
    $workspace = makeErpWorkspace();

    config([
        'services.n8n.base_url' => 'https://n8n.test',
        'services.n8n.api_key' => 'n8n-key',
    ]);

    Http::fake([
        'n8n.test/api/v1/executions/*/retry' => Http::response(['success' => true]),
        '*' => Http::response(['ok' => true]),
    ]);

    $batch = GencysSyncBatch::start($workspace->id);
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);
    $run->forceFill(['n8n_execution_id' => 4242])->save();
    $run->fail('scrape blew up');

    $replayed = app(RetryGencysSyncRuns::class)->forBatch($batch->fresh());

    expect($replayed)->toBe(1);

    // n8n was asked to replay the execution, loading the current workflow version.
    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/api/v1/executions/4242/retry'
        && $request->method() === 'POST'
        && $request['loadWorkflow'] === true
        && $request->header('X-N8N-API-KEY')[0] === 'n8n-key');

    $run->refresh();
    $batch->refresh();

    // The same run is reopened — the replay re-posts the original payload, so no
    // second run is created and the batch goes back to running.
    expect($run->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and($run->finished_at)->toBeNull()
        ->and($run->message)->toContain('4242')
        ->and($batch->total_runs)->toBe(1)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('runs sharing one execution trigger a single n8n retry call', function () {
    $workspace = makeErpWorkspace();

    config([
        'services.n8n.base_url' => 'https://n8n.test',
        'services.n8n.api_key' => 'n8n-key',
    ]);

    Http::fake(['*' => Http::response(['success' => true])]);

    $batch = GencysSyncBatch::start($workspace->id);

    // A chunk of items all come back from the same n8n execution.
    foreach (['A', 'B', 'C'] as $sku) {
        $item = InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => "SKU-{$sku}",
            'is_active' => true,
        ]);

        $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/14/2026'], $batch->id);
        $run->forceFill(['n8n_execution_id' => 555])->save();
        $run->fail('timed out');
    }

    $replayed = app(RetryGencysSyncRuns::class)->forBatch($batch->fresh());

    expect($replayed)->toBe(3);

    // Three runs, one execution — one API call, not three.
    Http::assertSentCount(1);
});

test('when the n8n API rejects the replay the run falls back to a fresh scrape', function () {
    $workspace = makeErpWorkspace();

    config([
        'services.n8n.base_url' => 'https://n8n.test',
        'services.n8n.api_key' => 'n8n-key',
        'services.n8n.gencys_daily_sales_webhook_url' => 'https://n8n.test/webhook/gencys',
    ]);

    Http::fake([
        // The execution was pruned, so n8n can't replay it.
        'n8n.test/api/v1/*' => Http::response(['message' => 'Not Found'], 404),
        '*' => Http::response(['ok' => true]),
    ]);

    $batch = GencysSyncBatch::start($workspace->id);
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);
    $run->forceFill(['n8n_execution_id' => 9999])->save();
    $run->fail('timed out');

    $replayed = app(RetryGencysSyncRuns::class)->forBatch($batch->fresh());

    expect($replayed)->toBe(1);

    // A replaced scrape went out instead, opening a new run alongside the old one.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/webhook/gencys'));

    $batch->refresh();

    expect($batch->total_runs)->toBe(2)
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('with no n8n API configured retries fall back to re-scraping', function () {
    $workspace = makeErpWorkspace();

    config([
        'services.n8n.base_url' => null,
        'services.n8n.api_key' => null,
        'services.n8n.gencys_daily_sales_webhook_url' => 'https://n8n.test/webhook/gencys',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);

    $batch = GencysSyncBatch::start($workspace->id);
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/14/2026'], $batch->id);
    $run->forceFill(['n8n_execution_id' => 1234])->save();
    $run->fail('timed out');

    app(RetryGencysSyncRuns::class)->forBatch($batch->fresh());

    // Never tried to reach the API; went straight to the webhook.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/executions'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/webhook/gencys'));
});

test('every batched type sends the discriminator the n8n switch routes on', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config([
        'services.n8n.gencys_daily_sales_webhook_url' => 'https://n8n.test/webhook/gencys-sync',
        'services.n8n.transaction_history_webhook_url' => 'https://n8n.test/webhook/gencys-sync',
    ]);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-TYPE',
        'is_active' => true,
    ]);

    $this->artisan('gencys-erp:sync', [
        '--force' => true,
        '--sync' => true,
        '--type' => ['daily_sales_tracker', 'transaction_history'],
    ])->assertSuccessful();

    // Without `type` the consolidated workflow's Switch matches no branch and
    // the execution silently does nothing.
    Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'daily_sales_tracker');
    Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'transaction_history');
});

test('the fetch command runs all three types when no --type is given', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys-sync']);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-ALL',
        'is_active' => true,
    ]);

    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--force' => true,
        '--sync' => true,
        '--date' => '2026-08-14',
    ])->assertSuccessful();

    expect(GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_TRANSACTION_HISTORY)->count())->toBe(1)
        ->and(GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_DAILY_SALES_TRACKER)->count())->toBe(1)
        ->and(GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->count())->toBe(1);

    // Each type routes itself through the shared workflow.
    foreach (['transaction_history', 'daily_sales_tracker', 'purchase_order'] as $type) {
        Http::assertSent(fn ($request) => ($request['type'] ?? null) === $type);
    }
});

test('the fetch command honours --type', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys-sync']);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-ONE',
        'is_active' => true,
    ]);

    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--type' => ['purchase_order'],
        '--force' => true,
        '--sync' => true,
    ])->assertSuccessful();

    expect(GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->count())->toBe(1)
        ->and(GencysSyncRun::where('sync_type', '!=', GencysSyncRun::TYPE_PURCHASE_ORDER)->count())->toBe(0);
});

test('the fetch command rejects an unknown --type', function () {
    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--type' => ['nonsense'],
        '--force' => true,
    ])->assertFailed();
});

test('purchase-order runs record the date range they asked for', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys-sync']);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-RANGE',
        'is_active' => true,
    ]);

    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--type' => ['purchase_order'],
        '--force' => true,
        '--sync' => true,
        '--start-date' => '2026-05-01',
        '--end-date' => '2026-08-01',
    ])->assertSuccessful();

    $run = GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->sole();

    // The retry path reads these back, so the m/d/Y shape matters.
    expect($run->meta['start_date'])->toBe('05/01/2026')
        ->and($run->meta['end_date'])->toBe('08/01/2026');
});

test('an unreachable webhook still opens every run for the rest of the sweep', function () {
    $workspace = makeErpWorkspace();

    // Every outbound call throws, the way an unreachable n8n does. Under --sync
    // the job runs inline, so an unguarded throw would abort the command on the
    // very first chunk and the later chunks would never open a run at all.
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys-sync']);

    foreach (range(1, 25) as $i) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => "SKU-{$i}",
            'is_active' => true,
        ]);
    }

    // 25 items chunk into 2 calls, so the second one only happens if the first
    // failure was contained.
    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--type' => ['purchase_order'],
        '--force' => true,
        '--sync' => true,
    ])->assertSuccessful();

    $runs = GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_PURCHASE_ORDER)->get();

    expect($runs)->toHaveCount(25)
        // The job failed its own runs on the way out, so none are left pending.
        ->and($runs->where('status', GencysSyncRun::STATUS_FAILED))->toHaveCount(25);
});

test('a queued call re-stamps started_at when it actually fires', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);

    // A run opened with the sweep, then left sitting in the queue behind the
    // chunk stagger. gencys-erp:expire-stale-sync-runs measures its 3h timeout
    // from started_at, so the clock has to start when the webhook goes out.
    $run = GencysSyncRun::start(
        $workspace->id,
        null,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        ['date' => '08/14/2026'],
    );
    $run->forceFill(['started_at' => now()->subHours(2)])->save();

    (new FetchDailySalesTrackerJob('https://n8n.test/webhook/gencys-sync', [], [$run->id]))->handle();

    expect($run->fresh()->started_at->diffInMinutes(now(), true))->toBeLessThan(1);
});

test('each type sends n8n exactly the payload keys its branch reads', function () {
    $workspace = makeErpWorkspace();

    Http::fake(['*' => Http::response(['ok' => true])]);
    config(['services.n8n.webhook_url' => 'https://n8n.test/webhook/gencys-sync']);

    InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-PAYLOAD',
        'is_active' => true,
    ]);

    $this->artisan('gencys-erp:trigger-fetch-data', [
        '--force' => true,
        '--sync' => true,
        '--date' => '2026-08-14',
    ])->assertSuccessful();

    // The consolidated workflow reads these by name, so the shared basePayload()
    // and each strategy's own keys have to add up to exactly this per branch.
    $sent = [];

    Http::assertSent(function ($request) use (&$sent) {
        $sent[$request['type']] = array_keys($request->data());

        return true;
    });

    expect($sent['transaction_history'])->toEqualCanonicalizing([
        'type', 'workspace_id', 'workspace_api_key', 'erp_username', 'erp_password',
        'webhook_url', 'date', 'items',
    ])->and($sent['purchase_order'])->toEqualCanonicalizing([
        'type', 'workspace_id', 'workspace_api_key', 'erp_username', 'erp_password',
        'webhook_url', 'start_date', 'end_date', 'delivered_purchase_orders_no', 'items',
    ])->and($sent['daily_sales_tracker'])->toEqualCanonicalizing([
        'type', 'workspace_id', 'workspace_api_key', 'erp_username', 'erp_password',
        'webhook_url', 'workspace_slug', 'date', 'sync_run_id',
    ]);
});
