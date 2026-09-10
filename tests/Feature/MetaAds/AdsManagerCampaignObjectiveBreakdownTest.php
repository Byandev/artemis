<?php

use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * One ad per campaign, each campaign carrying an objective (or none). The
 * objective lives on meta_ads_campaigns and ads reference the campaign
 * directly, so the breakdown reaches it in one hop rather than via the ad set.
 *
 * @param  array<int, array{objective: ?string, spend: float}>  $ads
 */
function seedCampaignObjectiveBreakdown($workspace, array $ads): AdAccount
{
    $metaUser = MetaUser::create(['id' => 9401, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 801, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $today = Carbon::today()->toDateString();
    $base = 8000;

    foreach ($ads as $index => $spec) {
        $id = $base + ($index * 10);

        $campaign = Campaign::create([
            'id' => $id,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$id}",
            'objective' => $spec['objective'],
            'start_time' => Carbon::today()->subDays(3),
        ]);

        $set = AdSet::create([
            'id' => $id + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'name' => "Ad Set {$id}",
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

function objectiveDataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

/**
 * The payload the Filters builder sends for objective rows — the same
 * `metric_filters` envelope the numeric filters ride in.
 *
 * @param  array<int, array{0: string, 1: string}>  $rows  [op, value] pairs
 */
function objectiveFilters(array $rows): string
{
    return json_encode(array_map(
        fn ($r) => ['field' => 'campaign_objective', 'op' => $r[0], 'value' => $r[1]],
        $rows,
    ));
}

it('groups ads by the objective of their campaign', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        // Second ad on the same objective: the two spends collapse into one row.
        ['objective' => 'OUTCOME_SALES', 'spend' => 40],
        ['objective' => 'OUTCOME_ENGAGEMENT', 'spend' => 25],
    ]);

    $data = $this->getJson(objectiveDataUrl($workspace, ['group_by' => 'campaign_objective']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);

    // Default sort is -spend, so OUTCOME_SALES (140) leads.
    expect($data[0]['name'])->toBe('OUTCOME_SALES')
        ->and($data[0]['id'])->toBe('OUTCOME_SALES')
        ->and((float) $data[0]['spend'])->toBe(140.0)
        ->and($data[0]['ads_count'])->toBe(2)
        ->and($data[1]['name'])->toBe('OUTCOME_ENGAGEMENT')
        ->and((float) $data[1]['spend'])->toBe(25.0)
        ->and($data[1]['ads_count'])->toBe(1);
});

it('collects campaigns with no objective in an unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_TRAFFIC', 'spend' => 10],
        ['objective' => null, 'spend' => 90],
        ['objective' => null, 'spend' => 5],
    ]);

    $data = collect($this->getJson(objectiveDataUrl($workspace, ['group_by' => 'campaign_objective']))
        ->assertOk()->json('rows.data'))->keyBy('name');

    expect($data)->toHaveCount(2)
        ->and((float) $data['Unassigned objective']['spend'])->toBe(95.0)
        ->and($data['Unassigned objective']['ads_count'])->toBe(2)
        // "0" so the drill-down can pass the bucket back as a scope value.
        ->and($data['Unassigned objective']['id'])->toBe('0')
        ->and((float) $data['OUTCOME_TRAFFIC']['spend'])->toBe(10.0);
});

it('searches the campaign-objective breakdown by objective', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_AWARENESS', 'spend' => 25],
    ]);

    $data = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign_objective',
        'filter' => ['search' => 'AWARENESS'],
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('OUTCOME_AWARENESS');
});

it('drills a campaign-objective row down to its ads', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_SALES', 'spend' => 40],
        ['objective' => null, 'spend' => 7],
    ]);

    $data = $this->getJson(objectiveDataUrl($workspace, [
        'scope_by' => 'campaign_objective',
        'scope' => 'OUTCOME_SALES',
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->sum(fn ($row) => (float) $row['spend']))->toBe(140.0);

    // The unassigned bucket drills down too, on the id the grid showed.
    $unassigned = $this->getJson(objectiveDataUrl($workspace, [
        'scope_by' => 'campaign_objective',
        'scope' => '0',
    ]))->assertOk()->json('rows.data');

    expect($unassigned)->toHaveCount(1)
        ->and((float) $unassigned[0]['spend'])->toBe(7.0);
});

// Unlike the optimization-goal breakdown, which reads start_time off the ad
// set, this one already joins the campaign and reads the campaign's own date.
it('filters the campaign-objective breakdown by started date through the campaign', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [['objective' => 'OUTCOME_SALES', 'spend' => 100]]);

    // Campaigns start 3 days ago, so an "after yesterday" filter excludes them.
    $excluded = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign_objective',
        'date_filters' => json_encode([[
            'field' => 'started_date',
            'op' => 'after',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($excluded)->toBeEmpty();

    // ...while a "before yesterday" filter keeps them.
    $kept = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign_objective',
        'date_filters' => json_encode([[
            'field' => 'started_date',
            'op' => 'before',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($kept)->toHaveCount(1);
});

// Ad-grained like every other breakdown that isn't the campaign itself, so
// created_date reads the ad's own column rather than the campaign's.
it('filters the campaign-objective breakdown by created date through the ad', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [['objective' => 'OUTCOME_SALES', 'spend' => 100]]);
    Ad::query()->update(['created_time' => Carbon::today()->subDays(3)]);

    // Ads were created 3 days ago, so an "after yesterday" filter excludes them.
    $excluded = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign_objective',
        'date_filters' => json_encode([[
            'field' => 'created_date',
            'op' => 'after',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($excluded)->toBeEmpty();

    // ...while a "before yesterday" filter keeps them.
    $kept = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign_objective',
        'date_filters' => json_encode([[
            'field' => 'created_date',
            'op' => 'before',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($kept)->toHaveCount(1);
});

// ── Objective filter (independent of grouping) ──────────────────────────────

it('filters any breakdown to the selected campaign objectives', function (string $groupBy) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_AWARENESS', 'spend' => 25],
    ]);

    $rows = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => $groupBy,
        'metric_filters' => objectiveFilters([['is', 'OUTCOME_SALES']]),
    ]))->assertOk()->json('rows.data');

    // Only the sales campaign's ad survives, and only its spend is summed.
    expect(collect($rows)->sum(fn ($r) => (float) $r['spend']))->toBe(100.0);
})->with(['ad', 'ad_name', 'campaign', 'ad_set', 'campaign_objective']);

it('excludes an objective with the is-not operator', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_AWARENESS', 'spend' => 25],
        ['objective' => 'OUTCOME_TRAFFIC', 'spend' => 7],
    ]);

    $rows = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign',
        'metric_filters' => objectiveFilters([['is_not', 'OUTCOME_AWARENESS']]),
    ]))->assertOk()->json('rows.data');

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->sum(fn ($r) => (float) $r['spend']))->toBe(107.0);
});

// "0" is the sentinel the breakdown shows for campaigns with no objective, so
// the filter has to accept the same value back.
it('filters to campaigns with no objective via the 0 sentinel', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => null, 'spend' => 9],
    ]);

    $rows = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'campaign',
        'metric_filters' => objectiveFilters([['is', '0']]),
    ]))->assertOk()->json('rows.data');

    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['spend'])->toBe(9.0);
});

// An account has no objective of its own. It survives if it ran a matching
// campaign, but its metrics must not keep summing the excluded ones.
it('limits account rows to spend from matching campaigns only', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_AWARENESS', 'spend' => 25],
    ]);

    $rows = $this->getJson(objectiveDataUrl($workspace, [
        'group_by' => 'account',
        'metric_filters' => objectiveFilters([['is', 'OUTCOME_SALES']]),
    ]))->assertOk()->json('rows.data');

    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['spend'])->toBe(100.0);
});

it('returns everything when no objective is selected', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_AWARENESS', 'spend' => 25],
    ]);

    $rows = $this->getJson(objectiveDataUrl($workspace, ['group_by' => 'campaign']))
        ->assertOk()->json('rows.data');

    expect(collect($rows)->sum(fn ($r) => (float) $r['spend']))->toBe(125.0);
});

it('offers the workspace objectives to the picker', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedCampaignObjectiveBreakdown($workspace, [
        ['objective' => 'OUTCOME_SALES', 'spend' => 100],
        ['objective' => 'OUTCOME_SALES', 'spend' => 10],
        ['objective' => null, 'spend' => 5],
    ]);

    $objectives = $this->get(route('workspaces.metaads.ads-manager', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['objectives'];

    // Distinct, with the unassigned bucket last and on the same "0" sentinel.
    expect($objectives)->toBe([
        ['value' => 'OUTCOME_SALES', 'label' => 'OUTCOME_SALES'],
        ['value' => '0', 'label' => 'Unassigned objective'],
    ]);
});
