<?php

use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Jobs\SyncAdAccountPeople;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdAccountPerson;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;

/** One connected Meta user owning one ad account. */
function seedPeopleAccount(?string $businessId = '5001', int $accountId = 778000111): AdAccount
{
    $metaUser = MetaUser::create(['id' => 7301, 'name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::create([
        'id' => $accountId,
        'name' => 'People Account',
        'business_id' => $businessId,
    ]);
    $metaUser->adAccounts()->attach($account->id);

    return $account;
}

/** A single-page assigned_users response. */
function assignedUsers(array $rows): array
{
    return ['data' => $rows, 'paging' => ['cursors' => ['after' => 'A']]];
}

it('stores each assigned user with the role derived from their Meta tasks', function () {
    $account = seedPeopleAccount();

    Http::fake([
        'graph.facebook.com/*/assigned_users*' => Http::response(assignedUsers([
            ['id' => '10001', 'name' => 'Alice Admin', 'tasks' => ['MANAGE', 'ADVERTISE']],
            ['id' => '10002', 'name' => 'Bob Buyer', 'tasks' => ['ADVERTISE', 'ANALYZE']],
            ['id' => '10003', 'name' => 'Cara Viewer', 'tasks' => ['ANALYZE']],
            ['id' => '10004', 'name' => 'Sys User', 'user_type' => 'SYSTEM_USER', 'tasks' => ['MANAGE']],
        ]), 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    $people = AdAccountPerson::where('meta_ads_account_id', $account->id)
        ->pluck('role', 'name');

    expect($people->all())->toBe([
        'Alice Admin' => 'Admin',
        'Bob Buyer' => 'Advertiser',
        'Cara Viewer' => 'Analyst',
        'Sys User' => 'Admin',
    ]);

    expect(AdAccountPerson::where('meta_user_id', '10004')->first()->user_type)
        ->toBe('SYSTEM_USER');
});

it('skips people Meta returns without a usable name, and records how many', function () {
    $account = seedPeopleAccount();

    Http::fake([
        'graph.facebook.com/*/assigned_users*' => Http::response(assignedUsers([
            ['id' => '20001', 'name' => 'Real Person', 'tasks' => ['ADVERTISE']],
            ['id' => '20002', 'name' => null, 'tasks' => ['ANALYZE']],
            ['id' => '20003', 'tasks' => ['MANAGE']],
            ['id' => '20004', 'name' => '   ', 'tasks' => ['ANALYZE']],
            ['id' => '20005', 'name' => 'Facebook User', 'tasks' => ['ANALYZE']],
        ]), 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    expect(AdAccountPerson::where('meta_ads_account_id', $account->id)->pluck('name')->all())
        ->toBe(['Real Person']);

    $run = SyncRun::where('entity_type', SyncRun::ENTITY_AD_ACCOUNT_PEOPLE)->latest('id')->first();
    expect($run->status)->toBe(SyncRun::STATUS_SUCCESS)
        ->and($run->records_synced)->toBe(1)
        ->and($run->meta['skipped_unnamed'])->toBe(4);
});

it('keeps unnamed people when skip_unnamed is disabled', function () {
    config()->set('metaads.people.skip_unnamed', false);
    $account = seedPeopleAccount();

    Http::fake([
        'graph.facebook.com/*/assigned_users*' => Http::response(assignedUsers([
            ['id' => '30001', 'name' => 'Real Person', 'tasks' => ['ADVERTISE']],
            ['id' => '30002', 'name' => null, 'tasks' => ['ANALYZE']],
        ]), 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    expect(AdAccountPerson::where('meta_ads_account_id', $account->id)->count())->toBe(2)
        ->and(AdAccountPerson::where('meta_user_id', '30002')->first()->name)->toBeNull();
});

it('falls back to the agencies edge when the account has no owning business', function () {
    $account = seedPeopleAccount(businessId: null);

    Http::fake([
        'graph.facebook.com/*/agencies*' => Http::response([
            'data' => [['id' => '9001', 'name' => 'Agency BM']],
            'paging' => ['cursors' => ['after' => 'A']],
        ], 200),
        'graph.facebook.com/*/assigned_users*' => Http::response(assignedUsers([
            ['id' => '40001', 'name' => 'Agency Person', 'tasks' => ['ADVERTISE']],
        ]), 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    $person = AdAccountPerson::where('meta_ads_account_id', $account->id)->first();
    expect($person->name)->toBe('Agency Person')
        ->and($person->source_business_id)->toBe('9001');

    // assigned_users was reached through the agency's business id.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'assigned_users')
        && str_contains($request->url(), 'business=9001'));
});

it('records a skipped run for an account in no portfolio at all', function () {
    $account = seedPeopleAccount(businessId: null);

    Http::fake([
        'graph.facebook.com/*/agencies*' => Http::response(['data' => []], 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    expect(AdAccountPerson::where('meta_ads_account_id', $account->id)->count())->toBe(0);

    $run = SyncRun::where('entity_type', SyncRun::ENTITY_AD_ACCOUNT_PEOPLE)->latest('id')->first();
    expect($run->status)->toBe(SyncRun::STATUS_SUCCESS)
        ->and($run->meta['skipped'])->toBe('no_business');
});

it('drops people whose access was revoked since the last run', function () {
    $account = seedPeopleAccount();

    AdAccountPerson::create([
        'meta_ads_account_id' => $account->id,
        'meta_user_id' => '50999',
        'name' => 'Departed Person',
        'role' => 'Advertiser',
    ]);

    Http::fake([
        'graph.facebook.com/*/assigned_users*' => Http::response(assignedUsers([
            ['id' => '50001', 'name' => 'Still Here', 'tasks' => ['MANAGE']],
        ]), 200),
    ]);

    (new SyncAdAccountPeople($account))->handle();

    expect(AdAccountPerson::where('meta_ads_account_id', $account->id)->pluck('name')->all())
        ->toBe(['Still Here']);
});
