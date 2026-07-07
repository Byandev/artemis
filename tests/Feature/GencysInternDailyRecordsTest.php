<?php

use Illuminate\Support\Facades\Queue;
use Modules\GencysERP\Jobs\FetchInternDailyRecordsJob;
use Modules\GencysERP\Models\GencysIntern;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\GencysSyncRun;

test('the trigger opens a pending sync run per synced intern and queues a fetch job', function () {
    config(['services.n8n.gencys_intern_daily_records_webhook_url' => 'https://n8n.test/webhook/records']);
    Queue::fake();

    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);
    makeApiKey($workspace);

    GencysIntern::factory()->for($workspace)->create(['intern_id' => 101]);
    GencysIntern::factory()->for($workspace)->create(['intern_id' => 102]);
    GencysIntern::factory()->for($workspace)->create(['intern_id' => null]); // never synced → excluded

    $this->artisan('gencys-erp:trigger-fetch-intern-daily-records', [
        '--force' => true,
        '--date' => '2026-07-06',
    ])->assertSuccessful();

    Queue::assertPushed(FetchInternDailyRecordsJob::class, 2); // one job per intern

    expect(
        GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_INTERN_DAILY_RECORDS)->pending()->count()
    )->toBe(2);

    // Each job carries a single intern_id (not a batched list).
    Queue::assertPushed(FetchInternDailyRecordsJob::class, fn ($job) => isset($job->data['intern_id'])
        && ! isset($job->data['interns']));
});

test('the --intern option limits which interns are fetched', function () {
    config(['services.n8n.gencys_intern_daily_records_webhook_url' => 'https://n8n.test/webhook/records']);
    Queue::fake();

    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);
    makeApiKey($workspace);
    GencysIntern::factory()->for($workspace)->create(['intern_id' => 101]);
    GencysIntern::factory()->for($workspace)->create(['intern_id' => 102]);

    $this->artisan('gencys-erp:trigger-fetch-intern-daily-records', [
        '--force' => true,
        '--intern' => ['101'],
    ])->assertSuccessful();

    expect(
        GencysSyncRun::where('sync_type', GencysSyncRun::TYPE_INTERN_DAILY_RECORDS)->count()
    )->toBe(1);
});

test('the callback upserts daily metrics per intern and resolves the sync run', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $intern = GencysIntern::factory()->for($workspace)->create(['intern_id' => 101]);
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_INTERN_DAILY_RECORDS, ['intern_id' => 101]);

    $payload = [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'records' => [
                [
                    'intern_id' => 101,
                    'sync_run_id' => $run->id,
                    'date' => '07/06/2026',
                    'sales' => '1,500.00',
                    'roas' => '2.35',
                    'ad_spent' => '640.00',
                    'rts_rate' => '12.50',
                    'rts_amount' => '320.00',
                ],
                ['intern_id' => 999, 'date' => '07/06/2026', 'sales' => '10'], // unknown intern → skipped
            ],
        ],
    ];

    $this->postJson('/api/v1/public/gencys/intern-daily-records', $payload)
        ->assertOk()
        ->assertJson(['saved' => 1, 'skipped' => 1]);

    $record = GencysInternDailyRecord::where('gencys_intern_id', $intern->id)->first();
    expect($record)->not->toBeNull()
        ->and((float) $record->sales)->toBe(1500.0)
        ->and((float) $record->roas)->toBe(2.35)
        ->and((float) $record->ad_spent)->toBe(640.0)
        ->and((float) $record->rts_rate)->toBe(12.5)
        ->and((float) $record->rts_amount)->toBe(320.0)
        ->and($record->record_date->toDateString())->toBe('2026-07-06');

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS);
});

test('re-syncing the same intern and day updates rather than duplicates', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $intern = GencysIntern::factory()->for($workspace)->create(['intern_id' => 101]);

    $post = fn (string $sales) => $this->postJson('/api/v1/public/gencys/intern-daily-records', [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'records' => [['intern_id' => 101, 'date' => '2026-07-06', 'sales' => $sales]],
        ],
    ]);

    $post('100.00')->assertOk();
    $post('250.00')->assertOk();

    expect(GencysInternDailyRecord::where('gencys_intern_id', $intern->id)->count())->toBe(1)
        ->and((float) GencysInternDailyRecord::where('gencys_intern_id', $intern->id)->first()->sales)->toBe(250.0);
});

test('the callback rejects a bad api key', function () {
    $this->postJson('/api/v1/public/gencys/intern-daily-records', [
        'data' => ['workspace_id' => 1, 'api_key' => 'art_bad', 'records' => []],
    ])->assertStatus(401);
});
