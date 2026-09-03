<?php

use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * One campaign holding two ads, with per-day insights so the timeline has
 * something to sum across ads and spread across dates.
 *
 * @param  array<int, array{ad: int, date: string, spend: float, impressions?: int, purchase_value?: float}>  $insights
 */
function seedTimeseries($workspace, array $insights): array
{
    $metaUser = MetaUser::create(['id' => 9201, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 601, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $campaign = Campaign::create([
        'id' => 6100,
        'meta_ads_account_id' => $account->id,
        'name' => 'Campaign A',
    ]);

    $set = AdSet::create([
        'id' => 6101,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'Ad Set A',
    ]);

    foreach ([1, 2] as $n) {
        Ad::create([
            'id' => 6100 + $n,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => "Ad {$n}",
        ]);
    }

    foreach ($insights as $i) {
        Insight::create([
            'meta_ads_ad_id' => $i['ad'],
            'date' => $i['date'],
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'spend' => $i['spend'],
            'impressions' => $i['impressions'] ?? 0,
            'purchase_value' => $i['purchase_value'] ?? 0,
        ]);
    }

    return ['account' => $account, 'campaign' => $campaign, 'set' => $set];
}

function timeseriesUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.timeseries', ['workspace' => $workspace, ...$query]);
}

it('returns one point per day across the range, summing every ad in the group', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $day1 = Carbon::today()->subDays(2)->toDateString();
    $day3 = Carbon::today()->toDateString();

    ['campaign' => $campaign] = seedTimeseries($workspace, [
        // Two ads on the same day: the point sums them.
        ['ad' => 6101, 'date' => $day1, 'spend' => 60, 'impressions' => 400],
        ['ad' => 6102, 'date' => $day1, 'spend' => 40, 'impressions' => 600],
        // Nothing on day 2 — it must still appear, zeroed.
        ['ad' => 6101, 'date' => $day3, 'spend' => 25, 'impressions' => 100],
    ]);

    $points = $this->getJson(timeseriesUrl($workspace, [
        'scope_by' => 'campaign',
        'scope' => (string) $campaign->id,
        'since' => $day1,
        'until' => $day3,
    ]))->assertOk()->json('points');

    expect($points)->toHaveCount(3)
        ->and($points[0]['date'])->toBe($day1)
        ->and((float) $points[0]['spend'])->toBe(100.0)
        ->and((float) $points[0]['impressions'])->toBe(1000.0)
        // The gap day is present and zeroed rather than missing.
        ->and((float) $points[1]['spend'])->toBe(0.0)
        ->and((float) $points[1]['impressions'])->toBe(0.0)
        ->and($points[2]['date'])->toBe($day3)
        ->and((float) $points[2]['spend'])->toBe(25.0);
});

it('scopes the timeline to a single ad', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $today = Carbon::today()->toDateString();

    seedTimeseries($workspace, [
        ['ad' => 6101, 'date' => $today, 'spend' => 60],
        ['ad' => 6102, 'date' => $today, 'spend' => 40],
    ]);

    $points = $this->getJson(timeseriesUrl($workspace, [
        'scope_by' => 'ad',
        'scope' => '6101',
        'since' => $today,
        'until' => $today,
    ]))->assertOk()->json('points');

    // Only the requested ad's spend, not the group's 100.
    expect($points)->toHaveCount(1)
        ->and((float) $points[0]['spend'])->toBe(60.0);
});

it('carries the raw columns computed metrics are derived from', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $today = Carbon::today()->toDateString();

    ['campaign' => $campaign] = seedTimeseries($workspace, [
        ['ad' => 6101, 'date' => $today, 'spend' => 50, 'purchase_value' => 200],
    ]);

    $points = $this->getJson(timeseriesUrl($workspace, [
        'scope_by' => 'campaign',
        'scope' => (string) $campaign->id,
        'since' => $today,
        'until' => $today,
    ]))->assertOk()->json('points');

    // The frontend divides these two for ROAS, so both must be present.
    expect((float) $points[0]['spend'])->toBe(50.0)
        ->and((float) $points[0]['purchase_value'])->toBe(200.0);
});

it('rejects a request with no scope', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedTimeseries($workspace, []);

    $this->getJson(timeseriesUrl($workspace))->assertStatus(400);
});

it('refuses a workspace the user is not a member of', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedTimeseries($workspace, []);

    $other = makeWorkspaceWithOwner()['workspace'];

    $this->getJson(timeseriesUrl($other, [
        'scope_by' => 'campaign',
        'scope' => '6100',
    ]))->assertStatus(403);
});
