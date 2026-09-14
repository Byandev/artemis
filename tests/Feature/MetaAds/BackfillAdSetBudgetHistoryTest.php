<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Jobs\BackfillAdSetBudgets;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\BudgetSnapshot;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * An ad set on an account we hold a token for. $current is the budget it holds
 * right now — the anchor the whole reconstruction walks backwards from.
 */
function seedBackfillAdSet(float $current, ?string $createdTime = null, ?string $startTime = null): AdSet
{
    $metaUser = MetaUser::firstOrCreate(['id' => 8201], ['name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::firstOrCreate(['id' => 771000333], ['name' => 'Backfill Account']);
    $metaUser->adAccounts()->syncWithoutDetaching([$account->id]);

    $campaign = Campaign::firstOrCreate(
        ['id' => 771000334],
        ['meta_ads_account_id' => $account->id, 'name' => 'C', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
    );

    return AdSet::create([
        'id' => 771000335,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'Backfill AS',
        'daily_budget' => $current,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
        'created_time' => $createdTime,
        'start_time' => $startTime,
    ]);
}

/**
 * One update_ad_set_budget activity, in the envelope the live API actually
 * sends — the amount is nested inside old_value/new_value and repeats the outer
 * key, in minor units (10000 == PHP 100.00). Verified against a real account.
 */
function budgetEvent(int $adSetId, string $eventTime, int $oldMinor, int $newMinor): array
{
    return [
        'event_type' => 'update_ad_set_budget',
        'event_time' => $eventTime,
        'object_id' => (string) $adSetId,
        'object_name' => 'Backfill AS',
        'extra_data' => json_encode([
            'old_value' => [
                'type' => 'payment_amount',
                'currency' => 'PHP',
                'old_value' => $oldMinor,
                'additional_type' => 'status_string',
                'additional_value' => 'Per day',
            ],
            'new_value' => [
                'type' => 'payment_amount',
                'currency' => 'PHP',
                'new_value' => $newMinor,
                'additional_type' => 'status_string',
                'additional_value' => 'Per day',
            ],
            'type' => 'composite_data',
        ]),
        'actor_name' => 'Some Buyer',
    ];
}

/** The same event with the amount sitting bare on the outer key. */
function flatBudgetEvent(int $adSetId, string $eventTime, int $old, int $new): array
{
    $event = budgetEvent($adSetId, $eventTime, $old, $new);
    $event['extra_data'] = json_encode(['old_value' => $old, 'new_value' => $new]);

    return $event;
}

/**
 * Run the backfill for the seeded account the way the queue would. The command
 * only dispatches; the work lives in the job.
 */
function runBackfill(int $days = 5, int $maxPages = 200, bool $dryRun = false): void
{
    $account = AdAccount::findOrFail(771000333);

    (new BackfillAdSetBudgets($account, $days, $maxPages, $dryRun))->handle();
}

/** This ad set's rows in the polymorphic snapshot table. */
function adSetSnapshots(int $adSetId)
{
    return BudgetSnapshot::where('entity_type', BudgetSnapshot::ENTITY_AD_SET)
        ->where('entity_id', $adSetId);
}

/** A second ad account on the same Meta user, to test staggering. */
function secondBackfillAccount(): AdAccount
{
    $account = AdAccount::firstOrCreate(['id' => 771000999], ['name' => 'Second Account']);
    MetaUser::findOrFail(8201)->adAccounts()->syncWithoutDetaching([$account->id]);

    return $account;
}

function fakeActivities(array $events): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => $events,
            'paging' => ['cursors' => ['after' => 'CURSOR']], // no `next` => last page
        ], 200),
    ]);
}

beforeEach(function () {
    // Freeze time so "5 days ago" lines up with the fixture event timestamps.
    Carbon::setTestNow('2026-09-14 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reconstructs the budget for each day from the change events', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    // Raised 500 → 750 on the 11th; before that it sat at 500.
    fakeActivities([budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 5);

    $byDate = adSetSnapshots($adSet->id)
        ->get()
        ->keyBy(fn ($row) => $row->date->toDateString())
        ->map(fn ($row) => (float) $row->daily_budget);

    expect($byDate['2026-09-10'])->toBe(500.00)
        ->and($byDate['2026-09-11'])->toBe(750.00)
        ->and($byDate['2026-09-13'])->toBe(750.00)
        // Today belongs to the live capture, not to a reconstruction.
        ->and($byDate->has('2026-09-14'))->toBeFalse();
});

it('carries the current budget across the window when nothing ever changed', function () {
    $adSet = seedBackfillAdSet(current: 300.00);

    fakeActivities([]);

    runBackfill(days: 3);

    $rows = adSetSnapshots($adSet->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('daily_budget')->map(fn ($b) => (float) $b)->unique()->all())->toBe([300.00]);
});

it('applies the latest change of a day, so a date holds where the budget ended up', function () {
    $adSet = seedBackfillAdSet(current: 900.00);

    // Two edits on the 12th — the day should end on 900, not 600.
    fakeActivities([
        budgetEvent($adSet->id, '2026-09-12 09:00:00', 40000, 60000),
        budgetEvent($adSet->id, '2026-09-12 18:00:00', 60000, 90000),
    ]);

    runBackfill(days: 4);

    $byDate = adSetSnapshots($adSet->id)
        ->get()
        ->keyBy(fn ($row) => $row->date->toDateString())
        ->map(fn ($row) => (float) $row->daily_budget);

    expect($byDate['2026-09-11'])->toBe(400.00)
        ->and($byDate['2026-09-12'])->toBe(900.00)
        ->and($byDate['2026-09-13'])->toBe(900.00);
});

it('never overwrites a row the live capture already observed', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    // The live capture already recorded the 12th as 512.00.
    BudgetSnapshot::create([
        'entity_type' => BudgetSnapshot::ENTITY_AD_SET,
        'entity_id' => $adSet->id,
        'date' => '2026-09-12',
        'daily_budget' => 512.00,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
        'source' => BudgetSnapshot::SOURCE_CAPTURE,
    ]);

    fakeActivities([budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 5);

    $kept = adSetSnapshots($adSet->id)->where('date', '2026-09-12')->first();

    expect((float) $kept->daily_budget)->toBe(512.00)
        ->and($kept->source)->toBe(BudgetSnapshot::SOURCE_CAPTURE);
});

it('marks reconstructed rows as backfill so they are distinguishable from observed ones', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    fakeActivities([budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 3);

    expect(adSetSnapshots($adSet->id)->pluck('source')->unique()->all())
        ->toBe([BudgetSnapshot::SOURCE_BACKFILL]);
});

it('starts the history at the ad set start time, not at its creation', function () {
    // Built on the 9th but not scheduled to run until the 12th — the days in
    // between bought nothing, so they get no budget row.
    $adSet = seedBackfillAdSet(current: 750.00, createdTime: '2026-09-09 08:00:00', startTime: '2026-09-12 08:00:00');

    fakeActivities([]);

    runBackfill(days: 10);

    $dates = adSetSnapshots($adSet->id)
        ->orderBy('date')
        ->pluck('date')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2026-09-12', '2026-09-13']);
});

it('falls back to the creation time when the ad set has no start time', function () {
    $adSet = seedBackfillAdSet(current: 750.00, createdTime: '2026-09-12 08:00:00', startTime: null);

    fakeActivities([]);

    runBackfill(days: 10);

    $dates = adSetSnapshots($adSet->id)
        ->orderBy('date')
        ->pluck('date')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2026-09-12', '2026-09-13']);
});

it('records the whole window for an ad set that started before it', function () {
    $adSet = seedBackfillAdSet(current: 750.00, createdTime: '2026-05-01 08:00:00', startTime: '2026-05-02 08:00:00');

    fakeActivities([]);

    runBackfill(days: 3);

    expect(adSetSnapshots($adSet->id)->count())->toBe(3);
});

it('skips an ad set whose events disagree with its current budget', function () {
    // Current budget is 750, but the newest event ends at 400 — something
    // changed the budget outside the activity log, so the walk backwards from
    // 750 would be wrong for every date.
    $adSet = seedBackfillAdSet(current: 750.00);

    fakeActivities([budgetEvent($adSet->id, '2026-09-11 14:00:00', 30000, 40000)]);

    runBackfill(days: 5);

    expect(adSetSnapshots($adSet->id)->exists())->toBeFalse();
});

it('reads a flat payload whose amounts are already in major units', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    // Not the shape the live API sends, but the parser accepts a bare amount on
    // the outer key, and the scale is settled against the current budget — so
    // 500 → 750 is read as pesos rather than misread as centavos.
    fakeActivities([flatBudgetEvent($adSet->id, '2026-09-11 14:00:00', 500, 750)]);

    runBackfill(days: 5);

    $row = adSetSnapshots($adSet->id)->where('date', '2026-09-10')->first();

    expect((float) $row->daily_budget)->toBe(500.00);
});

it('writes nothing on a dry run', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    fakeActivities([budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 5, dryRun: true);

    expect(BudgetSnapshot::count())->toBe(0);
});

it('writes nothing when probing, and reports the oldest event it can see', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    fakeActivities([
        budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000),
        budgetEvent($adSet->id, '2026-07-02 09:00:00', 20000, 50000),
    ]);

    $this->artisan('metaads:backfill-adset-budgets', ['--days' => 90, '--probe' => true])
        ->expectsOutputToContain('2026-07-02 09:00:00')
        ->assertSuccessful();

    expect(BudgetSnapshot::count())->toBe(0);
});

it('ignores CBO ad sets, which have no budget of their own', function () {
    $metaUser = MetaUser::firstOrCreate(['id' => 8201], ['name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::firstOrCreate(['id' => 771000333], ['name' => 'Backfill Account']);
    $metaUser->adAccounts()->syncWithoutDetaching([$account->id]);
    $campaign = Campaign::create(['id' => 771000340, 'meta_ads_account_id' => $account->id, 'name' => 'CBO', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => 750.00]);
    AdSet::create([
        'id' => 771000341,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'CBO AS',
        'daily_budget' => null,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    fakeActivities([]);

    runBackfill(days: 5);

    expect(BudgetSnapshot::count())->toBe(0);
});

it('refuses to reconstruct past where a capped fetch actually reached', function () {
    $adSet = seedBackfillAdSet(current: 750.00);

    // `next` present means Meta has more pages. --max-pages=1 stops us after the
    // first, so edits older than 09-11 were never seen — and since Meta returns
    // newest first, those are exactly the ones missing. Dates before that must
    // be left alone rather than filled in from an incomplete picture.
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [budgetEvent($adSet->id, '2026-09-11 14:00:00', 50000, 75000)],
            'paging' => [
                'cursors' => ['after' => 'CURSOR'],
                'next' => 'https://graph.facebook.com/v25.0/act_771000333/activities?after=CURSOR',
            ],
        ], 200),
    ]);

    runBackfill(days: 10, maxPages: 1);

    $dates = adSetSnapshots($adSet->id)
        ->orderBy('date')
        ->pluck('date')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2026-09-11', '2026-09-12', '2026-09-13']);
});

it('queues one job per ad account instead of doing the work inline', function () {
    Queue::fake();

    seedBackfillAdSet(current: 750.00);
    secondBackfillAccount();

    $this->artisan('metaads:backfill-adset-budgets', ['--days' => 30])->assertSuccessful();

    Queue::assertPushed(BackfillAdSetBudgets::class, 2);
    Queue::assertPushedOn('meta-ads', BackfillAdSetBudgets::class);

    // Nothing is written by the command itself.
    expect(BudgetSnapshot::count())->toBe(0);
});

it('staggers each account job so they do not all hit the API at once', function () {
    Queue::fake();

    seedBackfillAdSet(current: 750.00);
    secondBackfillAccount();

    $this->artisan('metaads:backfill-adset-budgets', ['--days' => 30, '--delay' => 120])->assertSuccessful();

    $offsets = Queue::pushed(BackfillAdSetBudgets::class)
        ->map(fn ($job) => Carbon::instance($job->delay)->getTimestamp() - now()->getTimestamp())
        ->sort()
        ->values()
        ->all();

    expect($offsets)->toBe([0, 120]);
});

it('passes the window and caps through to the job', function () {
    Queue::fake();

    seedBackfillAdSet(current: 750.00);

    $this->artisan('metaads:backfill-adset-budgets', [
        '--days' => 45,
        '--max-pages' => 10,
        '--dry-run' => true,
    ])->assertSuccessful();

    Queue::assertPushed(
        BackfillAdSetBudgets::class,
        fn ($job) => $job->days === 45 && $job->maxPages === 10 && $job->dryRun === true,
    );
});

it('queues only the named ad account when one is given', function () {
    Queue::fake();

    seedBackfillAdSet(current: 750.00);
    secondBackfillAccount();

    $this->artisan('metaads:backfill-adset-budgets', ['ad_account' => 771000999])->assertSuccessful();

    Queue::assertPushed(BackfillAdSetBudgets::class, 1);
    Queue::assertPushed(BackfillAdSetBudgets::class, fn ($job) => (int) $job->adAccount->id === 771000999);
});

it('skips ad accounts whose sync is switched off', function () {
    Queue::fake();

    seedBackfillAdSet(current: 750.00);
    AdAccount::firstOrCreate(['id' => 771000998], ['name' => 'Paused', 'active_sync' => false]);

    $this->artisan('metaads:backfill-adset-budgets')->assertSuccessful();

    Queue::assertPushed(BackfillAdSetBudgets::class, 1);
    Queue::assertNotPushed(BackfillAdSetBudgets::class, fn ($job) => (int) $job->adAccount->id === 771000998);
});

it('tags the ad account once its budgets have been backfilled', function () {
    seedBackfillAdSet(current: 750.00);

    expect(AdAccount::find(771000333)->budgets_backfilled_at)->toBeNull();

    fakeActivities([budgetEvent(771000335, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 5);

    expect(AdAccount::find(771000333)->budgets_backfilled_at->toDateTimeString())
        ->toBe('2026-09-14 10:00:00');
});

it('tags the account even when it had no budget events to replay', function () {
    seedBackfillAdSet(current: 750.00);

    fakeActivities([]);

    runBackfill(days: 5);

    // Nothing changed in the window, but the window was still rebuilt from it —
    // the rows carry the current budget, so the account is genuinely backfilled.
    expect(AdAccount::find(771000333)->budgets_backfilled_at)->not->toBeNull();
});

it('leaves the tag alone on a dry run', function () {
    seedBackfillAdSet(current: 750.00);

    fakeActivities([budgetEvent(771000335, '2026-09-11 14:00:00', 50000, 75000)]);

    runBackfill(days: 5, dryRun: true);

    expect(AdAccount::find(771000333)->budgets_backfilled_at)->toBeNull();
});

it('does not tag an account whose fetch failed', function () {
    seedBackfillAdSet(current: 750.00);

    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'boom', 'code' => 100]], 400)]);

    expect(fn () => runBackfill(days: 5))->toThrow(MetaGraphException::class);

    expect(AdAccount::find(771000333)->budgets_backfilled_at)->toBeNull();
});
