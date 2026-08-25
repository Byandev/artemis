<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;

/** One daily sales tracker row as n8n scrapes it. */
function trackerRow(int $id): array
{
    return [
        'id' => $id,
        'No' => "ORD-{$id}",
        'Order Date' => '24/08/2026 10:00:00',
        'CSR' => 'Jane',
        'Customer Name' => 'A Customer',
        'Order' => '1x2X MAGNERVE',
        'Total Qty' => 1,
        'Price (Final)' => 1250,
    ];
}

/** Post one chunk of tracker rows the way n8n does. */
function postTrackerChunk(string $raw, int $workspaceId, int $runId, array $ids)
{
    return test()->postJson('/api/v1/public/gencys/daily-sales-tracker', [[
        'workspace_id' => $workspaceId,
        'api_key' => $raw,
        'sync_run_id' => $runId,
        'purchase_orders' => array_map(fn (int $id) => trackerRow($id), $ids),
    ]]);
}

/** An ERP-connected workspace plus the raw API key n8n calls back with. */
function makeTrackerWorkspace(): array
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass'])->save();

    ['raw' => $raw] = makeApiKey($workspace);

    return ['workspace' => $workspace, 'raw' => $raw];
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
});

test('a data chunk saves its rows but leaves the run open', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    postTrackerChunk($raw, $workspace->id, $run->id, [1, 2, 3])->assertOk();

    $run->refresh();

    expect(GencysDailySalesOrder::count())->toBe(3)
        ->and($run->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and($run->rows_received)->toBe(3)
        ->and($run->finished_at)->toBeNull();
});

test('chunks accumulate their row counts and push the callback deadline out', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);
    $run->markSent(600);

    $firstDeadline = $run->fresh()->timeout_at;

    $this->travel(5)->minutes();
    postTrackerChunk($raw, $workspace->id, $run->id, [1, 2])->assertOk();

    $this->travel(5)->minutes();
    postTrackerChunk($raw, $workspace->id, $run->id, [3, 4, 5])->assertOk();

    $run->refresh();

    // Ten minutes in, a run that started with a ten-minute deadline is still
    // alive because each chunk pushed it out.
    expect($run->rows_received)->toBe(5)
        ->and($run->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and($run->timeout_at->greaterThan($firstDeadline))->toBeTrue()
        ->and($run->timeout_at->greaterThan(now()))->toBeTrue();
});

test('the finish endpoint closes the run and keeps the accumulated totals', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    postTrackerChunk($raw, $workspace->id, $run->id, [1, 2])->assertOk();
    postTrackerChunk($raw, $workspace->id, $run->id, [3, 4, 5])->assertOk();

    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $run->id,
    ])->assertOk()->assertJson([
        'sync_run_id' => $run->id,
        'status' => GencysSyncRun::STATUS_SUCCESS,
        'rows_received' => 5,
    ]);

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(5)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->timeout_at)->toBeNull();
});

test('finish can override the totals when n8n tracks them itself', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'rows_received' => 4210,
        'rows_saved' => 4200,
    ])->assertOk();

    $run->refresh();

    expect($run->rows_received)->toBe(4210)->and($run->rows_saved)->toBe(4200);
});

test('finish can report a failure instead', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'status' => 'failed',
        'message' => 'ERP login rejected',
    ])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_FAILED)
        ->and($run->message)->toBe('ERP login rejected');
});

test('finishing twice is harmless and a late chunk does not reopen the run', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    postTrackerChunk($raw, $workspace->id, $run->id, [1, 2])->assertOk();

    $finish = fn () => $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $run->id,
    ]);

    $finish()->assertOk();
    $finishedAt = $run->fresh()->finished_at;

    $finish()->assertOk();

    // A duplicate post arriving after the run closed still saves its rows but
    // leaves the run alone.
    postTrackerChunk($raw, $workspace->id, $run->id, [3])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->finished_at->eq($finishedAt))->toBeTrue()
        ->and($run->rows_received)->toBe(2)
        ->and(GencysDailySalesOrder::count())->toBe(3);
});

test('the batch holds the ERP until finish is called', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();

    // Two dates: n8n takes one per call, so the second waits on the first.
    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_DAILY_SALES_TRACKER],
        [GencysSyncRun::TYPE_DAILY_SALES_TRACKER => ['dates' => ['08/23/2026', '08/24/2026']]],
    );

    $first = $batch->runs()->pending()->sole();

    // Chunks land, but the batch must not move on yet.
    postTrackerChunk($raw, $workspace->id, $first->id, [1, 2])->assertOk();
    postTrackerChunk($raw, $workspace->id, $first->id, [3, 4])->assertOk();

    expect($batch->runs()->pending()->pluck('id')->all())->toBe([$first->id])
        ->and($batch->runs()->queued()->count())->toBe(1);

    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $first->id,
    ])->assertOk();

    // Now the next date goes out.
    expect($first->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->pending()->sole()->id)->not->toBe($first->id)
        ->and($batch->fresh()->status)->toBe(GencysSyncBatch::STATUS_RUNNING);
});

test('finish rejects a bad key and a run from another workspace', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeTrackerWorkspace();
    ['workspace' => $other] = makeTrackerWorkspace();

    $otherRun = GencysSyncRun::start($other->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '08/24/2026']);

    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => 'not-a-key',
        'sync_run_id' => $otherRun->id,
    ])->assertUnauthorized();

    // A valid key can't close someone else's run.
    $this->postJson('/api/v1/public/gencys/sync-runs/finish', [
        'api_key' => $raw,
        'sync_run_id' => $otherRun->id,
    ])->assertNotFound();

    expect($otherRun->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
});
