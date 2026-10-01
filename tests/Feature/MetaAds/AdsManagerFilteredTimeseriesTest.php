<?php

use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * The shape the row-timeline chart is opened over: one optimization goal spread
 * across campaigns with different objectives, so a goal row's ads are only
 * partly inside an objective filter. Each ad gets its own campaign and ad set;
 * insights are per day so the chart has more than one point to plot.
 *
 * @param  array<int, array{goal: ?string, objective: ?string, days: array<string, float>}>  $ads
 */
function seedFilteredTimeseries($workspace, array $ads): AdAccount
{
    $metaUser = MetaUser::create(['id' => 9501, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 901, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $base = 9000;

    foreach ($ads as $index => $spec) {
        $id = $base + ($index * 10);

        $campaign = Campaign::create([
            'id' => $id,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$id}",
            'objective' => $spec['objective'],
        ]);

        $set = AdSet::create([
            'id' => $id + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'optimization_goal' => $spec['goal'],
            'name' => "Ad Set {$id}",
        ]);

        $ad = Ad::create([
            'id' => $id + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => "Ad {$id}",
        ]);

        foreach ($spec['days'] as $date => $spend) {
            Insight::create([
                'meta_ads_ad_id' => $ad->id,
                'date' => $date,
                'meta_ads_account_id' => $account->id,
                'meta_ads_campaign_id' => $campaign->id,
                'meta_ads_set_id' => $set->id,
                'spend' => $spend,
                'impressions' => 1000,
                'clicks' => 50,
            ]);
        }
    }

    return $account;
}

function filteredTimeseriesUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.timeseries', ['workspace' => $workspace, ...$query]);
}

function filteredGridUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

/**
 * The `metric_filters` envelope the Filters builder sends for objective rows.
 *
 * @param  array<int, array{0: string, 1: string}>  $rows  [op, value] pairs
 */
function timelineObjectiveFilters(array $rows): string
{
    return json_encode(array_map(
        fn ($r) => ['field' => 'campaign_objective', 'op' => $r[0], 'value' => $r[1]],
        $rows,
    ));
}

/**
 * Grouped by optimization goal, filtered to OUTCOME_ENGAGEMENT: the CONVERSATIONS
 * row is fed by two campaigns, only one of which is an engagement campaign. The
 * chart for that row must plot the same 30 the row shows — not the 130 the goal
 * spent across both objectives.
 */
it('charts only the ads the active campaign-objective filter left in the row', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $day1 = Carbon::today()->subDay()->toDateString();
    $day2 = Carbon::today()->toDateString();

    seedFilteredTimeseries($workspace, [
        // Same goal, engagement objective — inside the filter.
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_ENGAGEMENT', 'days' => [$day1 => 10, $day2 => 20]],
        // Same goal, different objective — the filter must drop it.
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_SALES', 'days' => [$day1 => 100]],
        // Different goal entirely — outside the charted row either way.
        ['goal' => 'LINK_CLICKS', 'objective' => 'OUTCOME_ENGAGEMENT', 'days' => [$day2 => 7]],
    ]);

    $filters = timelineObjectiveFilters([['is', 'OUTCOME_ENGAGEMENT']]);

    // What the grid row shows: the goal, narrowed to engagement campaigns.
    $rows = collect($this->getJson(filteredGridUrl($workspace, [
        'group_by' => 'optimization_goal',
        'metric_filters' => $filters,
        'since' => $day1,
        'until' => $day2,
    ]))->assertOk()->json('rows.data'))->keyBy('name');

    expect((float) $rows['CONVERSATIONS']['spend'])->toBe(30.0);

    // What the chart behind that row's button plots.
    $points = $this->getJson(filteredTimeseriesUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => 'CONVERSATIONS',
        'metric_filters' => $filters,
        'since' => $day1,
        'until' => $day2,
    ]))->assertOk()->json('points');

    expect($points)->toHaveCount(2)
        ->and($points[0]['date'])->toBe($day1)
        ->and((float) $points[0]['spend'])->toBe(10.0)
        ->and($points[1]['date'])->toBe($day2)
        ->and((float) $points[1]['spend'])->toBe(20.0)
        // The chart total has to agree with the row it was opened from.
        ->and(collect($points)->sum(fn ($p) => (float) $p['spend']))
        ->toBe((float) $rows['CONVERSATIONS']['spend']);
});

it('excludes an objective from the chart with the is-not operator', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $today = Carbon::today()->toDateString();

    seedFilteredTimeseries($workspace, [
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_ENGAGEMENT', 'days' => [$today => 30]],
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_SALES', 'days' => [$today => 100]],
    ]);

    $points = $this->getJson(filteredTimeseriesUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => 'CONVERSATIONS',
        'metric_filters' => timelineObjectiveFilters([['is_not', 'OUTCOME_ENGAGEMENT']]),
        'since' => $today,
        'until' => $today,
    ]))->assertOk()->json('points');

    expect((float) $points[0]['spend'])->toBe(100.0);
});

// A goal row can hold nothing at all once the objective filter is applied. The
// chart still has to answer with zeroed days rather than an empty range.
it('zeroes the chart when the objective filter empties the row', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $today = Carbon::today()->toDateString();

    seedFilteredTimeseries($workspace, [
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_SALES', 'days' => [$today => 100]],
    ]);

    $points = $this->getJson(filteredTimeseriesUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => 'CONVERSATIONS',
        'metric_filters' => timelineObjectiveFilters([['is', 'OUTCOME_ENGAGEMENT']]),
        'since' => $today,
        'until' => $today,
    ]))->assertOk()->json('points');

    expect($points)->toHaveCount(1)
        ->and((float) $points[0]['spend'])->toBe(0.0);
});

it('charts the whole goal row when no objective filter is active', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $today = Carbon::today()->toDateString();

    seedFilteredTimeseries($workspace, [
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_ENGAGEMENT', 'days' => [$today => 30]],
        ['goal' => 'CONVERSATIONS', 'objective' => 'OUTCOME_SALES', 'days' => [$today => 100]],
    ]);

    $points = $this->getJson(filteredTimeseriesUrl($workspace, [
        'scope_by' => 'optimization_goal',
        'scope' => 'CONVERSATIONS',
        'since' => $today,
        'until' => $today,
    ]))->assertOk()->json('points');

    expect((float) $points[0]['spend'])->toBe(130.0);
});
