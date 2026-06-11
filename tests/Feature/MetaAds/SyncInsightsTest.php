<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;

function makeSyncableAdAccount(): AdAccount
{
    $metaUser = MetaUser::create(['id' => 7001, 'name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::create(['id' => 555000111, 'name' => 'Sync Account']);
    $metaUser->adAccounts()->attach($account->id);

    return $account;
}

function insightsRow(string $adId, string $date): array
{
    return [
        'ad_id' => $adId,
        'adset_id' => '222',
        'campaign_id' => '111',
        'date_start' => $date,
        'date_stop' => $date,
        'impressions' => '1000',
        'spend' => '12.34',
        'actions' => [['action_type' => 'omni_purchase', 'value' => '3']],
        'action_values' => [['action_type' => 'omni_purchase', 'value' => '99.00']],
    ];
}

it('does not dispatch a continuation when only cursors.after is present (no paging.next)', function () {
    Queue::fake();
    $account = makeSyncableAdAccount();
    $date = '2026-06-07';

    // A complete, single page: Meta still returns cursors.after, but there is no
    // paging.next, so this is the last page.
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [insightsRow('900900', $date)],
            'paging' => ['cursors' => ['before' => 'B', 'after' => 'A']],
        ], 200),
    ]);

    (new SyncInsights($account, $date))->handle();

    // Row persisted with the action-derived purchase metric...
    expect(Insight::where('meta_ads_ad_id', 900900)->value('purchases'))->toBe(3);
    // ...the run is marked success...
    expect(SyncRun::latest('id')->first()->status)->toBe(SyncRun::STATUS_SUCCESS);
    // ...and crucially no extra page job was queued.
    Queue::assertNotPushed(SyncInsights::class);
});

it('dispatches a continuation job only while paging.next is present', function () {
    Queue::fake();
    $account = makeSyncableAdAccount();
    $date = '2026-06-07';

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [insightsRow('900901', $date)],
            'paging' => [
                'cursors' => ['before' => 'B', 'after' => 'NEXTCURSOR'],
                'next' => 'https://graph.facebook.com/v25.0/act_555000111/insights?after=NEXTCURSOR',
            ],
        ], 200),
    ]);

    (new SyncInsights($account, $date))->handle();

    Queue::assertPushed(SyncInsights::class, function (SyncInsights $job) {
        return $job->afterCursor === 'NEXTCURSOR';
    });
});
