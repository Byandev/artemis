<?php

use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Jobs\SyncAds;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Creative;
use Modules\MetaAds\Models\User as MetaUser;

it('captures the creative thumbnail from each ad and upserts a creative row', function () {
    $metaUser = MetaUser::create(['id' => 7101, 'name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::create(['id' => 556000222, 'name' => 'Sync Account']);
    $metaUser->adAccounts()->attach($account->id);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'id' => '900900',
                'name' => 'Ad A',
                'adset_id' => '222',
                'campaign_id' => '111',
                'creative' => ['id' => '777', 'thumbnail_url' => 'https://example.test/thumb.jpg'],
                'status' => 'ACTIVE',
                'effective_status' => 'ACTIVE',
            ]],
            'paging' => ['cursors' => ['after' => 'A']], // present but no `next` => last page
        ], 200),
    ]);

    (new SyncAds($account))->handle();

    $ad = Ad::find(900900);
    expect($ad)->not->toBeNull()
        ->and((string) $ad->meta_ads_creative_id)->toBe('777');
    // The creative row exists (even though /adcreatives was never called) with the thumbnail.
    expect(Creative::find(777)?->thumbnail_url)->toBe('https://example.test/thumb.jpg');
});
