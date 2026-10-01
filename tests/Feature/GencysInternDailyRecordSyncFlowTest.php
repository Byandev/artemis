<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/**
 * Intern daily records on the batch queue.
 *
 * This is the widest-fanning flow there is — one run per intern per date — so
 * what matters is that the fan-out is built from the synced roster and that each
 * run travels alone with its own intern and date. It replaces
 * TriggerFetchInternDailyRecordsCommand, whose payload the deployed n8n workflow
 * still reads, so the field names are pinned here too.
 */
function makeDailyRecordErpWorkspace(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass'])->save();
    ['raw' => $raw] = makeApiKey($workspace);

    return ['user' => $user, 'workspace' => $workspace, 'raw' => $raw];
}

function makeSyncedIntern($workspace, int $internId, bool $active = true): Intern
{
    return Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => $internId,
        'name' => "Intern {$internId}",
        'active' => $active,
    ]);
}

beforeEach(function () {
    config(['services.n8n.gencys_intern_daily_records_webhook_url' => 'https://n8n.test/webhook/intern-daily']);
    Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);
});

test('it is a sync type the batch queue owns and the form offers', function () {
    $registry = app(SyncFlowRegistry::class);

    expect($registry->for(GencysSyncRun::TYPE_INTERN_DAILY_RECORDS)->label())
        ->toBe('Intern daily records')
        ->and($registry->types())->toContain(GencysSyncRun::TYPE_INTERN_DAILY_RECORDS);
});

test('the batch form offers it', function () {
    ['user' => $user, 'workspace' => $workspace] = makeDailyRecordErpWorkspace();

    config(['inertia.ssr.enabled' => false]);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/gencys/sync-batches")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('syncTypes', 4)
            ->where('syncTypes', fn ($types) => collect($types)->pluck('value')
                ->contains(GencysSyncRun::TYPE_INTERN_DAILY_RECORDS))
        );
});

test('it opens one run per intern per date', function () {
    ['workspace' => $workspace] = makeDailyRecordErpWorkspace();

    makeSyncedIntern($workspace, 41);
    makeSyncedIntern($workspace, 42);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026', '08/11/2026']]],
    );

    // 2 interns × 2 dates.
    expect($batch->total_runs)->toBe(4);

    $subjects = $batch->runs()->get()->map(
        fn (GencysSyncRun $run) => data_get($run->meta, 'date').'#'.data_get($run->meta, 'intern_id')
    )->sort()->values()->all();

    expect($subjects)->toBe([
        '08/10/2026#41',
        '08/10/2026#42',
        '08/11/2026#41',
        '08/11/2026#42',
    ]);
});

test('it skips interns the roster has not synced, and inactive ones', function () {
    ['workspace' => $workspace] = makeDailyRecordErpWorkspace();

    makeSyncedIntern($workspace, 41);
    makeSyncedIntern($workspace, 42, active: false);
    // Never seen by the ERP — nothing to ask about.
    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => null,
        'name' => 'Local only',
        'active' => true,
    ]);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026']]],
    );

    expect($batch->total_runs)->toBe(1)
        ->and(data_get($batch->runs()->sole()->meta, 'intern_id'))->toBe(41);
});

test('it sends one intern and one date per call, in the shape the workflow reads', function () {
    ['workspace' => $workspace, 'raw' => $raw] = makeDailyRecordErpWorkspace();

    makeSyncedIntern($workspace, 41);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026']]],
    );

    $run = $batch->runs()->sole();

    // `api_key`, matching the retired command — not `workspace_api_key`.
    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/webhook/intern-daily'
        && $request['workspace_id'] === $workspace->id
        && $request['api_key'] === $raw
        && $request['erp_username'] === 'erp-user'
        && $request['intern_id'] === 41
        && $request['date'] === '08/10/2026'
        && $request['sync_run_id'] === $run->id
        && str_ends_with($request['webhook_url'], '/api/v1/public/gencys/intern-daily-records'));
});

test('only one call goes out at a time, however wide the fan-out', function () {
    ['workspace' => $workspace] = makeDailyRecordErpWorkspace();

    makeSyncedIntern($workspace, 41);
    makeSyncedIntern($workspace, 42);
    makeSyncedIntern($workspace, 43);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026']]],
    );

    expect($batch->total_runs)->toBe(3)
        // The whole point of moving off the fixed timer: three runs queued, one
        // ERP session open.
        ->and($batch->runs()->pending()->count())->toBe(1)
        ->and($batch->runs()->queued()->count())->toBe(2);

    Http::assertSentCount(1);
});

test('a retry re-asks for just that intern on that date', function () {
    ['workspace' => $workspace] = makeDailyRecordErpWorkspace();

    makeSyncedIntern($workspace, 41);
    makeSyncedIntern($workspace, 42);

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026', '08/11/2026']]],
    );

    $run = $batch->runs()->first();
    $flow = app(SyncFlowRegistry::class)->for(GencysSyncRun::TYPE_INTERN_DAILY_RECORDS);

    expect($flow->parametersForRun($run))->toBe([
        'dates' => [data_get($run->meta, 'date')],
        'intern_ids' => [data_get($run->meta, 'intern_id')],
    ]);
});

test('a workspace with no synced interns contributes nothing', function () {
    makeDailyRecordErpWorkspace();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS],
        [GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => ['dates' => ['08/10/2026']]],
    );

    // Nothing to ask for, so the empty batch is dropped rather than parked.
    expect($batch->exists)->toBeFalse();
    Http::assertNothingSent();
});
