<?php

use App\Jobs\AdsManager\FetchCampaigns;
use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\FacebookAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function setupAdsContext(): array
{
    $fb = FacebookAccount::create([
        'id' => '7100',
        'user_id' => User::factory()->create()->id,
        'name' => 'Acc',
        'email' => 'a@example.test',
        'access_token' => 'TOKEN',
    ]);

    $ad = AdAccount::create([
        'id' => '500',
        'name' => 'Ad Account',
        'currency' => 'USD',
        'status' => 1,
    ]);

    return [$fb, $ad];
}

test('FetchCampaigns upserts campaigns from a single page response', function () {
    Http::fake([
        'graph.facebook.com/v22.0/act_*/campaigns*' => Http::response([
            'data' => [
                [
                    'id' => 1001,
                    'account_id' => '500',
                    'name' => 'C1 Name',
                    'status' => 'ACTIVE',
                    'effective_status' => 'ACTIVE',
                    'daily_budget' => 10000,
                    'start_time' => '2026-04-01T00:00:00+0000',
                ],
                [
                    'id' => 1002,
                    'account_id' => '500',
                    'name' => 'C2 Name',
                    'status' => 'PAUSED',
                    'effective_status' => 'PAUSED',
                    'start_time' => '2026-04-01T00:00:00+0000',
                ],
            ],
        ], 200),
    ]);

    [$fb, $ad] = setupAdsContext();
    (new FetchCampaigns($fb, $ad))->handle();

    expect(Campaign::count())->toBe(2);
    expect((float) Campaign::find(1001)->daily_budget)->toBe(100.0);
    expect(Campaign::find(1002)->effective_status)->toBe('PAUSED');
});

test('FetchCampaigns paginates when paging.next is present', function () {
    Http::fake([
        'graph.facebook.com/v22.0/act_*/campaigns*' => Http::sequence()
            ->push([
                'data' => [['id' => 2001, 'account_id' => '500', 'name' => 'A', 'status' => 'X', 'effective_status' => 'X', 'start_time' => '2026-04-01T00:00:00+0000']],
                'paging' => ['next' => 'http://next', 'cursors' => ['after' => 'cursor-2']],
            ], 200)
            ->push([
                'data' => [['id' => 2002, 'account_id' => '500', 'name' => 'B', 'status' => 'X', 'effective_status' => 'X', 'start_time' => '2026-04-01T00:00:00+0000']],
            ], 200),
    ]);

    [$fb, $ad] = setupAdsContext();
    (new FetchCampaigns($fb, $ad))->handle();

    expect(Campaign::count())->toBe(2);
});

test('FetchCampaigns throws on upstream error (no swallow)', function () {
    Http::fake(['graph.facebook.com/*' => Http::response([], 500)]);

    [$fb, $ad] = setupAdsContext();

    expect(fn () => (new FetchCampaigns($fb, $ad))->handle())
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});
