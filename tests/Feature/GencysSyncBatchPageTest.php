<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\Inventory\Models\InventoryItem;

function syncBatchesUrl($workspace, string $suffix = ''): string
{
    return "/workspaces/{$workspace->slug}/gencys/sync-batches".$suffix;
}

/** An ERP-connected workspace with active items, plus its owner. */
function makeErpWorkspaceWithOwner(int $items = 2): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass'])->save();
    makeApiKey($workspace);

    foreach (range(1, $items) as $n) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => "SKU-{$n}",
            'is_active' => true,
            'is_parent' => false,
        ]);
    }

    return ['user' => $user, 'workspace' => $workspace];
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
});

test('the index lists batches with their progress and what is holding the ERP', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncBatchesUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/sync-batches/index')
            ->has('batches.data', 1)
            ->where('batches.data.0.status', GencysSyncBatch::STATUS_RUNNING)
            ->where('batches.data.0.sync_types', [GencysSyncRun::TYPE_TRANSACTION_HISTORY])
            ->where('batches.data.0.total_runs', 2)
            ->where('running.status', GencysSyncBatch::STATUS_RUNNING)
            ->where('queuedCount', 0)
            ->has('syncTypes', 3)
        );
});

test('the detail page lists the batch runs visible to this workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncBatchesUrl($workspace, "/{$batch->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/sync-batches/show')
            ->where('batch.id', $batch->id)
            ->has('runs.data', 2)
            ->where('runs.data.0.subject', 'SKU-1')
            // The same per-run actions as the Sync Runs page need these two.
            ->has('n8nApiConfigured')
            ->where('queueBusy', true)
            ->has('runs.data.0.n8n_execution_id')
        );
});

test('a batch raised from the UI is pinned to that workspace and attributed to the user', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();
    ['workspace' => $other] = makeErpWorkspaceWithOwner();

    $this->actingAs($user)
        ->post(syncBatchesUrl($workspace), [
            'sync_types' => [
                GencysSyncRun::TYPE_TRANSACTION_HISTORY,
                GencysSyncRun::TYPE_PURCHASE_ORDER,
            ],
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-24',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = GencysSyncBatch::sole();

    // One batch covering both types, each with its own window.
    expect($batch->sync_types)->toBe([
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
    ])
        ->and($batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY)['dates'])->toBe(['08/24/2026'])
        ->and($batch->parametersFor(GencysSyncRun::TYPE_PURCHASE_ORDER)['start_date'])->toBe('08/24/2026')
        ->and($batch->workspace_id)->toBe($workspace->id)
        ->and($batch->source)->toBe(GencysSyncBatch::SOURCE_MANUAL)
        ->and($batch->created_by_user_id)->toBe($user->id)
        ->and($batch->total_runs)->toBe(4)
        ->and($batch->runs()->where('workspace_id', $other->id)->count())->toBe(0);
});

test('an end date before the start date is rejected', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    $this->actingAs($user)
        ->post(syncBatchesUrl($workspace), [
            'sync_types' => [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-20',
        ])
        ->assertSessionHasErrors('end_date');

    expect(GencysSyncBatch::count())->toBe(0);
});

test('a sync type the batch queue does not own is rejected', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    $this->actingAs($user)
        ->post(syncBatchesUrl($workspace), [
            'sync_types' => [GencysSyncRun::TYPE_PAGE_DETAILS],
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-24',
        ])
        ->assertSessionHasErrors('sync_types.0');
});

test('the batch list can be filtered to batches covering one sync type', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    // A multi-type batch, then a transaction-history-only one behind it.
    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::TYPE_PURCHASE_ORDER],
        [
            GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']],
            GencysSyncRun::TYPE_PURCHASE_ORDER => ['start_date' => '08/01/2026', 'end_date' => '08/24/2026'],
        ],
    );

    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_DAILY_SALES_TRACKER],
        [GencysSyncRun::TYPE_DAILY_SALES_TRACKER => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncBatchesUrl($workspace).'?filter[sync_type]='.GencysSyncRun::TYPE_PURCHASE_ORDER)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('batches.data', 1));
});

test('cancelling from the UI stops the batch and releases the queue', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->post(syncBatchesUrl($workspace, "/{$batch->id}/cancel"))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($batch->fresh()->status)->toBe(GencysSyncBatch::STATUS_CANCELLED)
        ->and($batch->runs()->where('status', GencysSyncRun::STATUS_CANCELLED)->count())->toBe(2);
});

test('a finished batch cannot be cancelled again', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceWithOwner();

    $batch = GencysSyncBatch::create([
        'sync_types' => [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        'status' => GencysSyncBatch::STATUS_COMPLETED,
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(syncBatchesUrl($workspace, "/{$batch->id}/cancel"))
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($batch->fresh()->status)->toBe(GencysSyncBatch::STATUS_COMPLETED);
});

test('someone outside the workspace cannot see its sync batches', function () {
    ['workspace' => $workspace] = makeErpWorkspaceWithOwner();
    ['user' => $outsider] = makeWorkspaceWithOwner();

    $this->actingAs($outsider)
        ->get(syncBatchesUrl($workspace))
        ->assertForbidden();
});
