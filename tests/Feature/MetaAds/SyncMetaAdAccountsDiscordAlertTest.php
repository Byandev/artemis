<?php

use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Jobs\SyncMetaAdAccounts;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\User as MetaUser;

/** One connected Meta user plus a faked me/adaccounts page for the given rows. */
function seedAccountsSync(array $rows): MetaUser
{
    config()->set('services.discord.webhook_url', 'https://discord.test/app-wide');
    config()->set('services.discord.meta_ads_webhook_url', 'https://discord.test/meta-ads');

    Http::fake([
        'graph.facebook.com/*/adaccounts*' => Http::response([
            'data' => $rows,
            'paging' => ['cursors' => ['after' => 'A']],
        ], 200),
        'discord.test/*' => Http::response('', 204),
    ]);

    return MetaUser::create(['id' => 9101, 'name' => 'Alert Owner', 'access_token' => 'fake-token']);
}

/** The Discord webhook call as [url, body], or null when none was made. */
function discordCall(): ?array
{
    foreach (Http::recorded() as [$request]) {
        if (str_contains($request->url(), 'discord.test')) {
            return [$request->url(), $request->data()];
        }
    }

    return null;
}

it('alerts Discord about accounts that are syncing but not active on Meta', function () {
    $metaUser = seedAccountsSync([
        ['id' => 'act_991000111', 'account_id' => '991000111', 'name' => 'Healthy Account', 'account_status' => 1],
        ['id' => 'act_991000222', 'account_id' => '991000222', 'name' => 'Broke Account', 'account_status' => 2,
            'business' => ['id' => '5001', 'name' => 'Acme BM']],
        ['id' => 'act_991000333', 'account_id' => '991000333', 'name' => 'Unsettled Account', 'account_status' => 3],
    ]);

    (new SyncMetaAdAccounts($metaUser))->handle();

    $call = discordCall();

    expect($call)->not->toBeNull();

    [$url, $payload] = $call;

    expect($url)->toBe('https://discord.test/meta-ads');
    expect($payload['content'])->toBe('⚠️ Theres a problem with this ad accounts');
    expect($payload['embeds'][0]['title'])->toBe('2 ad account(s) need attention');

    $description = $payload['embeds'][0]['description'];
    expect($description)->toContain('Broke Account')
        ->toContain('act_991000222')
        ->toContain('Acme BM')
        ->toContain('Disabled')
        ->toContain('Unsettled Account')
        ->toContain('Unsettled')
        ->not->toContain('Healthy Account');
});

it('stays quiet for inactive accounts that have sync turned off', function () {
    AdAccount::create([
        'id' => '992000111',
        'name' => 'Paused Account',
        'account_status' => 2,
        'active_sync' => false,
    ]);

    $metaUser = seedAccountsSync([
        ['id' => 'act_992000111', 'account_id' => '992000111', 'name' => 'Paused Account', 'account_status' => 2],
    ]);

    (new SyncMetaAdAccounts($metaUser))->handle();

    expect(discordCall())->toBeNull();
});

it('stays quiet when every synced account is active', function () {
    $metaUser = seedAccountsSync([
        ['id' => 'act_993000111', 'account_id' => '993000111', 'name' => 'Healthy Account', 'account_status' => 1],
        ['id' => 'act_993000222', 'account_id' => '993000222', 'name' => 'Unknown Status'],
    ]);

    (new SyncMetaAdAccounts($metaUser))->handle();

    expect(discordCall())->toBeNull();
});
