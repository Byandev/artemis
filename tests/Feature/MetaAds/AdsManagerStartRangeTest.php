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
 * Two graphs created on the same day but scheduled to start three months
 * apart, both with insights today — so only `start_time` separates them.
 * Creation dates are deliberately identical to prove the created-range filter
 * isn't what's doing the work.
 */
function seedStartTimeGraph($workspace): array
{
    $metaUser = MetaUser::create(['id' => 9201, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 301, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $today = Carbon::today()->toDateString();

    $build = function (int $base, string $name, string $startTime) use ($account, $today) {
        $campaign = Campaign::create([
            'id' => $base,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$name}",
            'created_time' => '2026-05-01 09:00:00',
            'start_time' => $startTime,
        ]);
        $set = AdSet::create([
            'id' => $base + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'name' => "Ad Set {$name}",
            'created_time' => '2026-05-01 09:00:00',
            'start_time' => $startTime,
        ]);
        $ad = Ad::create([
            'id' => $base + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => $name,
            'created_time' => '2026-05-01 09:00:00',
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

        return ['ad' => $ad, 'set' => $set, 'campaign' => $campaign];
    };

    $early = $build(5000, 'Early Ad', '2026-05-10 09:00:00');
    $late = $build(6000, 'Late Ad', '2026-08-12 09:00:00');

    return ['account' => $account, 'early' => $early, 'late' => $late];
}

function startRangeUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

it('returns every ad when no start range is given', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, ['group_by' => 'ad']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);
});

it('narrows ads to those whose ad set started within the range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-08-01',
        'start_until' => '2026-08-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Late Ad');
});

it('accepts an open-ended start range with only a start', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-08-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Late Ad');
});

it('accepts an open-ended start range with only an end', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_until' => '2026-06-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Early Ad');
});

it('includes entities starting on the boundary dates themselves', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    // 09:00 on the boundary day must still match a date-only bound.
    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-08-12',
        'start_until' => '2026-08-12',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Late Ad');
});

it('applies the start range to campaign, ad set and ad name breakdowns too', function (string $groupBy) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => $groupBy,
        'start_since' => '2026-08-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1);
})->with(['campaign', 'ad_set', 'ad_name']);

it('leaves the account breakdown alone, which has no start date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    // Filtering by a window nothing started in must not blank the account row.
    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'account',
        'start_since' => '2030-01-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1);
});

it('returns nothing when no entity started in the range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2030-01-01',
        'start_until' => '2030-12-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toBeEmpty();
});

it('filters on the start date independently of the creation date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    // Both ads were created in May; only one started in August. A created-range
    // filter over May keeps both, so the single row proves start_time applied.
    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'created_since' => '2026-05-01',
        'created_until' => '2026-05-31',
        'start_since' => '2026-08-01',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Late Ad');
});

it('combines the start range with a name search', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-08-01',
        'filter' => ['search' => 'Early'],
    ]))->assertOk()->json('rows.data');

    // "Early Ad" matches the search but started outside the window.
    expect($data)->toBeEmpty();
});

it('falls back to the created date when Meta gave no start time', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedStartTimeGraph($workspace);

    // Mimic a seeded / pre-field-list row: no start time at all.
    DB::table('meta_ads_sets')->where('id', $ctx['late']['set']->id)->update([
        'start_time' => null,
        'created_time' => '2026-08-12 09:00:00',
    ]);

    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-08-01',
        'start_until' => '2026-08-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Late Ad');
});

it('still prefers the real start time over the created date when both are set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedStartTimeGraph($workspace);

    // Both ad sets were created in May, but only one is scheduled to start
    // then — a May window must not pull in the August starter.
    $data = $this->getJson(startRangeUrl($workspace, [
        'group_by' => 'ad',
        'start_since' => '2026-05-01',
        'start_until' => '2026-05-31',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Early Ad');
});
