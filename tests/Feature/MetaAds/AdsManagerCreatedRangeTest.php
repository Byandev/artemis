<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Two ads created three months apart, both with insights today, so only the
 * `created_time` filter can tell them apart — the since/until window can't.
 */
function seedCreatedAtGraph($workspace): array
{
    $metaUser = MetaUser::create(['id' => 9101, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 201, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $today = Carbon::today()->toDateString();

    $build = function (int $base, string $name, string $createdTime) use ($account, $today) {
        $campaign = Campaign::create([
            'id' => $base,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$name}",
            'created_time' => $createdTime,
        ]);
        $set = AdSet::create([
            'id' => $base + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'name' => "Ad Set {$name}",
            'created_time' => $createdTime,
        ]);
        $ad = Ad::create([
            'id' => $base + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => $name,
            'created_time' => $createdTime,
        ]);

        Insight::create([
            'meta_ads_ad_id' => $ad->id,
            'date' => $today,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'spend' => 100,
            'impressions' => 1000,
            'clicks' => 50,
        ]);

        return $ad;
    };

    $old = $build(3000, 'Old Ad', '2026-05-10 09:00:00');
    $recent = $build(4000, 'Recent Ad', '2026-08-12 09:00:00');

    return ['account' => $account, 'old' => $old, 'recent' => $recent];
}

function createdRangeUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

it('returns every ad when no created range is given', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, ['group_by' => 'ad']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);
});

it('narrows ads to those created within the range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-08-01',
        'created_until' => '2026-08-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Recent Ad');
});

it('accepts an open-ended range with only a start', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-08-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Recent Ad');
});

it('accepts an open-ended range with only an end', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_until' => '2026-06-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Old Ad');
});

it('includes ads created on the boundary dates themselves', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    // 09:00 on the boundary day must still match a date-only bound.
    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-08-12',
        'created_until' => '2026-08-12',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Recent Ad');
});

it('applies the range to campaign and ad set breakdowns too', function (string $groupBy) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => $groupBy,
        'created_since' => '2026-08-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1);
})->with(['campaign', 'ad_set']);

it('leaves the account breakdown alone, which has no creation date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    // Filtering by a window no entity was created in must not blank the
    // account row — accounts have no created_time to filter on.
    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'account',
        'created_since' => '2030-01-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1);
});

it('returns nothing when no entity falls in the range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2030-01-01',
        'created_until' => '2030-12-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toBeEmpty();
});

it('combines the created range with a name search', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatedAtGraph($workspace);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-08-01',
        'filter' => ['search' => 'Old'],
    ]))->assertOk()->json('rows.data');

    // "Old Ad" matches the search but falls outside the created window.
    expect($data)->toBeEmpty();
});

it('falls back to the row created_at when Meta gave no created_time', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedCreatedAtGraph($workspace);

    // Mimic a seeded / pre-field-list row: no Meta creation time at all.
    DB::table('meta_ads_ads')->where('id', $ctx['recent']->id)->update([
        'created_time' => null,
        'created_at' => '2026-08-12 09:00:00',
    ]);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-08-01',
        'created_until' => '2026-08-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Recent Ad');
});

it('still prefers the real created_time over created_at when both are set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedCreatedAtGraph($workspace);

    // Row inserted today, but the ad was really made back in May — the Meta
    // date must win, otherwise every synced ad looks brand new.
    DB::table('meta_ads_ads')->where('id', $ctx['old']->id)->update([
        'created_at' => Carbon::today()->toDateTimeString(),
    ]);

    $data = $this->getJson(createdRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-05-01',
        'created_until' => '2026-05-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Old Ad');
});
