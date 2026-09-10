<?php

use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * One ad per ad set, each ad set carrying an optimization goal (or none). The
 * goal lives on meta_ads_sets, so the breakdown reaches it ad -> ad set.
 *
 * @param  array<int, array{goal: ?string, spend: float}>  $ads
 */
function seedOptimizationGoalBreakdown($workspace, array $ads): AdAccount
{
    $metaUser = MetaUser::create(['id' => 9301, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 701, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $today = Carbon::today()->toDateString();
    $base = 7000;

    foreach ($ads as $index => $spec) {
        $id = $base + ($index * 10);

        $campaign = Campaign::create([
            'id' => $id,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$id}",
        ]);

        $set = AdSet::create([
            'id' => $id + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'optimization_goal' => $spec['goal'],
            'name' => "Ad Set {$id}",
            'start_time' => Carbon::today()->subDays(3),
        ]);

        $ad = Ad::create([
            'id' => $id + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => "Ad {$id}",
        ]);

        Insight::create([
            'meta_ads_ad_id' => $ad->id,
            'date' => $today,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'spend' => $spec['spend'],
            'impressions' => 1000,
            'clicks' => 50,
        ]);
    }

    return $account;
}

function goalDataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

it('groups ads by the optimization goal of their ad set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [
        ['goal' => 'CONVERSATIONS', 'spend' => 100],
        // Second ad on the same goal: the two spends collapse into one row.
        ['goal' => 'CONVERSATIONS', 'spend' => 40],
        ['goal' => 'LEAD_GENERATION', 'spend' => 25],
    ]);

    $data = $this->getJson(goalDataUrl($workspace, ['group_by' => 'optimization_goal']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);

    // Default sort is -spend, so CONVERSATIONS (140) leads.
    expect($data[0]['name'])->toBe('CONVERSATIONS')
        ->and($data[0]['id'])->toBe('CONVERSATIONS')
        ->and((float) $data[0]['spend'])->toBe(140.0)
        ->and($data[0]['ads_count'])->toBe(2)
        ->and($data[1]['name'])->toBe('LEAD_GENERATION')
        ->and((float) $data[1]['spend'])->toBe(25.0)
        ->and($data[1]['ads_count'])->toBe(1);
});

it('collects ad sets with no optimization goal in an unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [
        ['goal' => 'VALUE', 'spend' => 10],
        ['goal' => null, 'spend' => 90],
        ['goal' => null, 'spend' => 5],
    ]);

    $data = collect($this->getJson(goalDataUrl($workspace, ['group_by' => 'optimization_goal']))
        ->assertOk()->json('rows.data'))->keyBy('name');

    expect($data)->toHaveCount(2)
        ->and((float) $data['Unassigned goal']['spend'])->toBe(95.0)
        ->and($data['Unassigned goal']['ads_count'])->toBe(2)
        // "0" so the drill-down can pass the bucket back as a scope value.
        ->and($data['Unassigned goal']['id'])->toBe('0')
        ->and((float) $data['VALUE']['spend'])->toBe(10.0);
});

it('searches the optimization-goal breakdown by goal', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [
        ['goal' => 'MESSAGING_PURCHASE_CONVERSION', 'spend' => 100],
        ['goal' => 'LINK_CLICKS', 'spend' => 25],
    ]);

    $data = $this->getJson(goalDataUrl($workspace, [
        'group_by' => 'optimization_goal',
        'filter' => ['search' => 'LINK'],
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('LINK_CLICKS');
});

it('drills an optimization-goal row down to its ads', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [
        ['goal' => 'CONVERSATIONS', 'spend' => 100],
        ['goal' => 'CONVERSATIONS', 'spend' => 40],
        ['goal' => null, 'spend' => 7],
    ]);

    $data = $this->getJson(goalDataUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => 'CONVERSATIONS',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->sum(fn ($row) => (float) $row['spend']))->toBe(140.0);

    // The unassigned bucket drills down too, on the id the grid showed.
    $unassigned = $this->getJson(goalDataUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => '0',
    ]))->assertOk()->json('rows.data');

    expect($unassigned)->toHaveCount(1)
        ->and((float) $unassigned[0]['spend'])->toBe(7.0);
});

it('filters the optimization-goal breakdown by started date through the ad set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [['goal' => 'VALUE', 'spend' => 100]]);

    // Ad sets start 3 days ago, so an "after yesterday" filter excludes them.
    $data = $this->getJson(goalDataUrl($workspace, [
        'group_by' => 'optimization_goal',
        'date_filters' => json_encode([[
            'field' => 'started_date',
            'op' => 'after',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($data)->toBeEmpty();
});

// The goal lives on the ad set, but the breakdown groups ads, so created_date
// reads the ad's own column rather than the ad set's.
it('filters the optimization-goal breakdown by created date through the ad', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedOptimizationGoalBreakdown($workspace, [['goal' => 'VALUE', 'spend' => 100]]);
    Ad::query()->update(['created_time' => Carbon::today()->subDays(3)]);

    // Ads were created 3 days ago, so an "after yesterday" filter excludes them.
    $excluded = $this->getJson(goalDataUrl($workspace, [
        'group_by' => 'optimization_goal',
        'date_filters' => json_encode([[
            'field' => 'created_date',
            'op' => 'after',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($excluded)->toBeEmpty();

    // ...while a "before yesterday" filter keeps them.
    $kept = $this->getJson(goalDataUrl($workspace, [
        'group_by' => 'optimization_goal',
        'date_filters' => json_encode([[
            'field' => 'created_date',
            'op' => 'before',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($kept)->toHaveCount(1);
});
