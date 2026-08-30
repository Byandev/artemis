<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\Inventory\Models\InventoryItem;

function n8nRunUrl($workspace, GencysSyncRun $run, string $suffix): string
{
    return "/workspaces/{$workspace->slug}/gencys/sync-runs/{$run->id}{$suffix}";
}

/** An ERP-connected workspace with active items, plus its owner. */
function makeN8nWorkspace(int $items = 2): array
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

/** A finished transaction-history run, as a batch would have left it. */
function finishedRun($workspace, string $status = GencysSyncRun::STATUS_FAILED): GencysSyncRun
{
    // Transaction history syncs a whole date, so the run belongs to no one item.
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/24/2026']);

    $run->forceFill(['status' => $status, 'finished_at' => now(), 'n8n_execution_id' => '5150'])->save();

    return $run;
}

beforeEach(function () {
    config([
        'services.n8n.api_url' => 'https://n8n.test',
        'services.n8n.api_key' => 'n8n-key',
        'services.n8n.transaction_history_webhook_url' => 'https://n8n.test/webhook/th',
    ]);
});

test('the execution lookup reports what n8n says, with a link into the editor', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    $run = finishedRun($workspace);

    Http::fake(['n8n.test/api/v1/executions/*' => Http::response([
        'id' => 5150,
        'status' => 'error',
        'finished' => false,
        'startedAt' => '2026-08-24T09:31:00.000Z',
        'stoppedAt' => '2026-08-24T09:33:00.000Z',
        'workflowId' => 'wf-abc',
        'mode' => 'webhook',
    ], 200)]);

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson([
            'state' => 'found',
            'execution' => [
                'id' => '5150',
                'status' => 'error',
                'workflow_id' => 'wf-abc',
                'url' => 'https://n8n.test/workflow/wf-abc/executions/5150',
            ],
        ]);

    // The API key travels as n8n expects it.
    Http::assertSent(fn ($request) => $request->hasHeader('X-N8N-API-KEY', 'n8n-key'));
});

test('a run with no recorded execution says so instead of calling n8n', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();

    $run = finishedRun($workspace);
    $run->forceFill(['n8n_execution_id' => null])->save();

    Http::fake();

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson(['state' => 'unknown']);

    Http::assertNothingSent();
});

test('an execution n8n kept no record of reports not_found rather than erroring', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    $run = finishedRun($workspace);

    Http::fake(['n8n.test/*' => Http::response(['message' => 'Not Found'], 404)]);

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson(['state' => 'not_found']);
});

test('a rejected API key says so, rather than blaming pruning', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    $run = finishedRun($workspace);

    Http::fake(['n8n.test/*' => Http::response(['message' => 'unauthorized'], 401)]);

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson(['state' => 'unauthorized'])
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Settings'));
});

test('an unreachable n8n degrades quietly', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    $run = finishedRun($workspace);

    Http::fake(fn () => throw new ConnectionException('connection refused'));

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson(['state' => 'unreachable']);
});

test('without n8n API config the lookup says so, without calling out', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    $run = finishedRun($workspace);

    config(['services.n8n.api_url' => null, 'services.n8n.api_key' => null]);

    Http::fake();

    $this->actingAs($user)
        ->getJson(n8nRunUrl($workspace, $run, '/execution'))
        ->assertOk()
        ->assertJson(['state' => 'unconfigured']);

    Http::assertNothingSent();
});

test('retrying a failed run queues a fresh batch for just that item and date', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace(items: 3);

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $run = finishedRun($workspace);

    $this->actingAs($user)
        ->post(n8nRunUrl($workspace, $run, '/retry'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = GencysSyncBatch::sole();

    // Scoped to the one date the failed run was for, not the flow's usual window.
    expect($batch->sync_types)->toBe([GencysSyncRun::TYPE_TRANSACTION_HISTORY])
        ->and($batch->workspace_id)->toBe($workspace->id)
        ->and($batch->source)->toBe(GencysSyncBatch::SOURCE_MANUAL)
        ->and($batch->created_by_user_id)->toBe($user->id)
        ->and($batch->total_runs)->toBe(1)
        ->and($batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY))->toBe([
            'dates' => ['08/24/2026'],
        ]);

    // The original run is left exactly as it was — history, not a workspace.
    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED);
});

test('only a failed run can be retried', function (string $status) {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $run = finishedRun($workspace, $status);

    $this->actingAs($user)
        ->post(n8nRunUrl($workspace, $run, '/retry'))
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect(GencysSyncBatch::count())->toBe(0);
})->with([
    GencysSyncRun::STATUS_SUCCESS,
    GencysSyncRun::STATUS_CANCELLED,
    GencysSyncRun::STATUS_PENDING,
    GencysSyncRun::STATUS_QUEUED,
]);

test('a retry is refused while the queue is still busy', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $failed = finishedRun($workspace);

    // Something is holding the ERP, so a retry would only queue behind it.
    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/25/2026']]],
    );

    expect(GencysSyncBatch::query()->active()->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(n8nRunUrl($workspace, $failed, '/retry'))
        ->assertRedirect()
        ->assertSessionHas('warning');

    // Only the batch that was already running — no retry batch was added.
    expect(GencysSyncBatch::count())->toBe(1);

    // The batch page tells the UI to grey the button out for the same reason.
    $running = GencysSyncBatch::query()->running()->sole();

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/gencys/sync-batches/{$running->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queueBusy', true));
});

test('a second retry is refused because the first is still working', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $run = finishedRun($workspace);

    $this->actingAs($user)->post(n8nRunUrl($workspace, $run, '/retry'))->assertSessionHas('success');

    // The retry itself now holds the ERP, so the queue guard closes behind it.
    $this->actingAs($user)->post(n8nRunUrl($workspace, $run, '/retry'))->assertSessionHas('warning');

    expect(GencysSyncBatch::count())->toBe(1);
});

test('another workspace run is not reachable through this workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeN8nWorkspace();
    ['workspace' => $other] = makeN8nWorkspace();

    $foreign = finishedRun($other);

    Http::fake();

    $this->actingAs($user)->getJson(n8nRunUrl($workspace, $foreign, '/execution'))->assertNotFound();
    $this->actingAs($user)->post(n8nRunUrl($workspace, $foreign, '/retry'))->assertNotFound();
});
