<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/** One daily sales tracker row as n8n scrapes it. */
function dailySalesRow(int $id, string $orderNo = 'ORD-1'): array
{
    return [
        'id' => $id,
        'No' => $orderNo,
        'Order Date' => '28/06/2026 10:00:00',
        'CSR' => 'Jane',
        'Customer Name' => 'A Customer',
        'Order' => '1x2X MAGNERVE',
        'Total Qty' => 1,
        'Price (Final)' => 1250,
    ];
}

test('the trigger command queues a batch with a run per workspace and date, and sends only the first', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    makeApiKey($workspace);
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);

    Http::fake(['*' => Http::response(['ok' => true])]);

    $this->artisan('gencys-erp:trigger-fetch-daily-sales-tracker', [
        '--force' => true,
        '--sync' => true,
        '--start-date' => '2026-06-27',
        '--end-date' => '2026-06-28',
    ])->assertSuccessful();

    $runs = GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_DAILY_SALES_TRACKER)
        ->orderBy('id')
        ->get();

    $batch = GencysSyncBatch::sole();

    expect($runs)->toHaveCount(2)
        ->and($runs->pluck('meta.date')->all())->toBe(['06/27/2026', '06/28/2026'])
        ->and($runs->pluck('gencys_sync_batch_id')->unique()->all())->toBe([$batch->id])
        ->and($batch->status)->toBe(GencysSyncBatch::STATUS_RUNNING);

    // n8n takes one date per call, so only the first date is in flight — the
    // second waits for its callback.
    expect($runs->first()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and($runs->last()->status)->toBe(GencysSyncRun::STATUS_QUEUED);

    // The in-flight run's id rides along in the payload for n8n to echo back.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['sync_run_id'] === $runs->first()->id
        && $request['date'] === '06/27/2026');
});

test('the callback resolves the echoed run and records its row counts', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/28/2026']);

    $this->postJson('/api/v1/public/gencys/daily-sales-tracker', [[
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'purchase_orders' => [dailySalesRow(9001), dailySalesRow(9002, 'ORD-2')],
    ]])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(2)
        ->and($run->rows_saved)->toBe(2)
        ->and($run->finished_at)->not->toBeNull()
        ->and(GencysDailySalesOrder::count())->toBe(2);
});

test('a daily sales success resolves earlier stuck runs for the same date only', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    // Earlier attempts at the same date that never came back.
    $stuck = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/28/2026']);
    $failed = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/28/2026']);
    $failed->fail('No callback received within 3h (sync timed out)');

    // A different date is a real gap — left failed.
    $otherDate = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/27/2026']);
    $otherDate->fail('n8n webhook returned HTTP 500');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/28/2026']);

    $this->postJson('/api/v1/public/gencys/daily-sales-tracker', [[
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'purchase_orders' => [dailySalesRow(9001)],
    ]])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->message)->toContain("Resolved by sync run #{$run->id}")
        ->and($failed->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($otherDate->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED);
});

test('a daily sales callback without a sync_run_id still saves the orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_DAILY_SALES_TRACKER, ['date' => '06/28/2026']);

    $this->postJson('/api/v1/public/gencys/daily-sales-tracker', [[
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'purchase_orders' => [dailySalesRow(9001)],
    ]])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and(GencysDailySalesOrder::count())->toBe(1);
});
