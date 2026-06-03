<?php

use App\Jobs\AdsManager\FetchAds;
use App\Jobs\AdsManager\FetchAdSets;
use App\Jobs\AdsManager\FetchCampaigns;
use App\Jobs\FetchAdAccounts;
use App\Jobs\FetchAdRecords;
use App\Models\AdAccount;
use App\Models\FacebookAccount;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

function makeFacebookAccount(): FacebookAccount
{
    return FacebookAccount::create([
        'id' => '7000',
        'user_id' => User::factory()->create()->id,
        'name' => 'Acc',
        'email' => 'a@example.test',
        'access_token' => 'TOKEN',
    ]);
}

test('FetchAdAccounts upserts ad accounts and dispatches sub-jobs', function () {
    Bus::fake([FetchCampaigns::class, FetchAdSets::class, FetchAds::class, FetchAdRecords::class]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'id' => 'act_111',
                    'account_id' => '111',
                    'name' => 'Account One',
                    'currency' => 'USD',
                    'account_status' => 1,
                    'business_country_code' => 'US',
                ],
            ],
        ], 200),
    ]);

    $fb = makeFacebookAccount();
    (new FetchAdAccounts($fb))->handle();

    expect(AdAccount::where('id', '111')->exists())->toBeTrue();
    expect(AdAccount::find('111')->facebook_accounts()->count())->toBe(1);
    Bus::assertDispatched(FetchCampaigns::class);
    Bus::assertDispatched(FetchAdSets::class);
    Bus::assertDispatched(FetchAds::class);
    // 2 months of daily fetches dispatched
    Bus::assertDispatched(FetchAdRecords::class);
});

test('FetchAdAccounts handles empty data array', function () {
    Bus::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => []], 200)]);

    $fb = makeFacebookAccount();
    (new FetchAdAccounts($fb))->handle();

    expect(AdAccount::count())->toBe(0);
    Bus::assertNothingDispatched();
});
