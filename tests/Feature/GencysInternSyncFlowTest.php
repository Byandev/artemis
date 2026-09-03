<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/** An ERP-connected workspace, its owner, and the raw key n8n calls back with. */
function makeInternErpWorkspace(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass'])->save();
    ['raw' => $raw] = makeApiKey($workspace);

    return ['user' => $user, 'workspace' => $workspace, 'raw' => $raw];
}

/** Post the roster back the way the n8n interns workflow does — wrapped in `data`. */
function postInternRoster(array $data): TestResponse
{
    return test()->postJson('/api/v1/public/gencys/interns', ['data' => $data]);
}

beforeEach(function () {
    config(['services.n8n.gencys_interns_webhook_url' => 'https://n8n.test/webhook/interns']);
    Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);
});

test('an interns batch opens one run per ERP workspace and sends the roster payload', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeInternErpWorkspace();

    $batch = app(BatchRunner::class)->queue([GencysSyncRun::TYPE_INTERNS]);

    $run = $batch->runs()->sole();

    expect($batch->total_runs)->toBe(1)
        ->and($run->sync_type)->toBe(GencysSyncRun::TYPE_INTERNS)
        ->and($run->status)->toBe(GencysSyncRun::STATUS_PENDING)
        // The roster is the whole subject, so there is no window to record.
        ->and($run->meta)->toBeNull();

    // The field names are the ones the deployed workflow already reads —
    // `api_key`, not the `workspace_api_key` the dated flows send.
    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/webhook/interns'
        && $request['workspace_id'] === $workspace->id
        && $request['api_key'] === $raw
        && $request['erp_username'] === 'erp-user'
        && $request['sync_run_id'] === $run->id
        && str_ends_with($request['webhook_url'], '/api/v1/public/gencys/interns'));
});

test('the interns callback upserts the roster, closes its run and finishes the batch', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeInternErpWorkspace();

    $batch = app(BatchRunner::class)->queue([GencysSyncRun::TYPE_INTERNS]);
    $run = $batch->runs()->sole();

    postInternRoster([
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'sync_run_id' => $run->id,
        'interns' => [
            ['id' => 41, 'fullName' => 'Ann Cruz', 'email' => 'ann@example.test'],
            ['id' => 42, 'fullName' => 'Ben Diaz', 'email' => 'ben@example.test'],
        ],
    ])
        ->assertOk()
        ->assertJson(['sync_run_id' => $run->id, 'created' => 2]);

    expect(Intern::where('workspace_id', $workspace->id)->count())->toBe(2)
        ->and($run->refresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(2)
        ->and($run->rows_saved)->toBe(2)
        ->and($batch->refresh()->status)->toBe(GencysSyncBatch::STATUS_COMPLETED);
});

test('a callback echoing no run id still credits the interns run in flight', function () {
    // The deployed workflow does not know about sync_run_id yet, so the flow has
    // to close its run off the one thing that is unambiguous: a batch only ever
    // has one interns run out for a workspace at a time.
    ['workspace' => $workspace, 'raw' => $raw] = makeInternErpWorkspace();

    $batch = app(BatchRunner::class)->queue([GencysSyncRun::TYPE_INTERNS]);
    $run = $batch->runs()->sole();

    postInternRoster([
        'workspace_id' => $workspace->id,
        'api_key' => $raw,
        'interns' => [['id' => 7, 'fullName' => 'Cy Reyes']],
    ])->assertOk();

    expect($run->refresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($batch->refresh()->status)->toBe(GencysSyncBatch::STATUS_COMPLETED);
});

test('a rejected callback leaves the run alone to time out', function () {
    ['workspace' => $workspace] = makeInternErpWorkspace();

    $batch = app(BatchRunner::class)->queue([GencysSyncRun::TYPE_INTERNS]);
    $run = $batch->runs()->sole();

    postInternRoster([
        'workspace_id' => $workspace->id,
        'api_key' => 'not-a-real-key',
        'sync_run_id' => $run->id,
        'interns' => [['id' => 9, 'fullName' => 'Nope']],
    ])->assertStatus(401);

    expect($run->refresh()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and(Intern::count())->toBe(0);
});

test('the interns page button raises a batch rather than firing at n8n itself', function () {
    ['user' => $user, 'workspace' => $workspace] = makeInternErpWorkspace();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = GencysSyncBatch::sole();

    expect($batch->sync_types)->toBe([GencysSyncRun::TYPE_INTERNS])
        ->and($batch->source)->toBe(GencysSyncBatch::SOURCE_MANUAL)
        ->and($batch->created_by_user_id)->toBe($user->id)
        ->and($batch->workspace_id)->toBe($workspace->id)
        ->and($batch->parametersFor(GencysSyncRun::TYPE_INTERNS))->toBe([])
        ->and($batch->total_runs)->toBe(1);
});

test('a second interns sync collapses into the one already queued', function () {
    ['user' => $user, 'workspace' => $workspace] = makeInternErpWorkspace();

    // Something else is holding the ERP, so the interns batch stays queued and a
    // second press has an unstarted batch to collapse into.
    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertSessionHas('warning');

    expect(GencysSyncBatch::query()->whereJsonContains('sync_types', GencysSyncRun::TYPE_INTERNS)->count())->toBe(1);
});

test('the interns button refuses cleanly when the workspace has no ERP credentials', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(GencysSyncBatch::count())->toBe(0);
    Http::assertNothingSent();
});

test('the roster ignores any window a batch hands it', function () {
    ['user' => $user, 'workspace' => $workspace] = makeInternErpWorkspace();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/sync-batches", [
            'sync_types' => [
                GencysSyncRun::TYPE_TRANSACTION_HISTORY,
                GencysSyncRun::TYPE_INTERNS,
            ],
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = GencysSyncBatch::sole();

    // Every type is handed the window now; the roster simply reads none of it
    // — it asks for the whole list either way.
    expect($batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY)['dates'])
        ->toBe(['08/24/2026', '08/25/2026'])
        ->and($batch->parametersFor(GencysSyncRun::TYPE_INTERNS)['dates'])
        ->toBe(['08/24/2026', '08/25/2026'])
        // Two dated runs, and one for the roster however wide the window was.
        ->and($batch->runs()->where('sync_type', GencysSyncRun::TYPE_INTERNS)->count())->toBe(1)
        ->and($batch->total_runs)->toBe(3);
});

test('the roster is off the batch form but still owned by the queue', function () {
    ['user' => $user, 'workspace' => $workspace] = makeInternErpWorkspace();

    config(['inertia.ssr.enabled' => false]);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/gencys/sync-batches")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('syncTypes', 4)
            ->where('syncTypes', fn ($types) => collect($types)
                ->pluck('value')
                ->doesntContain(GencysSyncRun::TYPE_INTERNS))
        );

    // Still registered, so the Interns page can raise it and runs already on
    // record keep their label.
    expect(app(SyncFlowRegistry::class)->has(GencysSyncRun::TYPE_INTERNS))->toBeTrue();
});
