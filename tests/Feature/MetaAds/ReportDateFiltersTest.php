<?php

use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Row-level lifecycle date filters (`date_filters`) on the Ads Manager data
 * engine, which is what the Reports builder renders against. These constrain
 * WHICH rows are returned by the entity's own created/start date — unrelated to
 * `since`/`until`, which pick which insight days get summed.
 *
 * Alpha was created 2026-07-01 and started 2026-07-05.
 * Bravo was created 2026-07-10 and started 2026-07-15.
 */
function seedReportDates($workspace): array
{
    $metaUser = MetaUser::create(['id' => 7101, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 701, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $build = function (int $base, string $label, string $created, string $started) use ($account) {
        $campaign = Campaign::create([
            'id' => $base,
            'meta_ads_account_id' => $account->id,
            'name' => "{$label} Campaign",
            'created_time' => $created.' 09:00:00',
            'start_time' => $started.' 09:00:00',
        ]);

        $set = AdSet::create([
            'id' => $base + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'name' => "{$label} Ad Set",
            'created_time' => $created.' 09:00:00',
            'start_time' => $started.' 09:00:00',
        ]);

        $ad = Ad::create([
            'id' => $base + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'name' => "{$label} Ad",
            'created_time' => $created.' 09:00:00',
        ]);

        Insight::create([
            'meta_ads_ad_id' => $ad->id,
            'date' => '2026-07-20',
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'spend' => 100,
            'impressions' => 1000,
            'clicks' => 50,
        ]);

        return compact('campaign', 'set', 'ad');
    };

    return [
        'account' => $account,
        'alpha' => $build(7200, 'Alpha', '2026-07-01', '2026-07-05'),
        'bravo' => $build(7300, 'Bravo', '2026-07-10', '2026-07-15'),
    ];
}

function reportDataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', [
        'workspace' => $workspace,
        'since' => '2026-07-01',
        'until' => '2026-07-31',
        ...$query,
    ]);
}

/** The `name` of every row the engine returned. */
function rowNames($response): array
{
    return collect($response->json('rows.data'))->pluck('name')->sort()->values()->all();
}

it('filters campaigns by their created date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            ['field' => 'created_date', 'op' => 'on', 'value' => '2026-07-01'],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Alpha Campaign']);
});

it('filters campaigns by their started date over a range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            [
                'field' => 'started_date',
                'op' => 'between',
                'value' => '2026-07-14',
                'value2' => '2026-07-16',
            ],
        ]),
    ]))->assertOk();

    // Alpha started on the 5th, so only Bravo falls in the window.
    expect(rowNames($response))->toBe(['Bravo Campaign']);
});

it('supports before and after on the created date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $before = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            ['field' => 'created_date', 'op' => 'before', 'value' => '2026-07-10'],
        ]),
    ]))->assertOk();

    $after = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            ['field' => 'created_date', 'op' => 'after', 'value' => '2026-07-01'],
        ]),
    ]))->assertOk();

    expect(rowNames($before))->toBe(['Alpha Campaign']);
    expect(rowNames($after))->toBe(['Bravo Campaign']);
});

it('answers an ad-level started date through the owning ad set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    // Ads carry no start_time of their own — Bravo's ad qualifies because its
    // ad set started on the 15th.
    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'ad',
        'date_filters' => json_encode([
            ['field' => 'started_date', 'op' => 'on', 'value' => '2026-07-15'],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Bravo Ad']);
});

it('filters ads by their own created date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'ad',
        'date_filters' => json_encode([
            ['field' => 'created_date', 'op' => 'on', 'value' => '2026-07-10'],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Bravo Ad']);
});

it('ignores a date filter the breakdown cannot answer', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    // Accounts have neither date — the filter is dropped rather than erroring
    // or silently returning nothing.
    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'account',
        'date_filters' => json_encode([
            ['field' => 'started_date', 'op' => 'on', 'value' => '2026-07-05'],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Account A']);
});

it('drops malformed date filters instead of failing', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            ['field' => 'created_date', 'op' => 'on', 'value' => 'not-a-date'],
            ['field' => 'nonsense', 'op' => 'on', 'value' => '2026-07-01'],
            ['field' => 'created_date', 'op' => 'sideways', 'value' => '2026-07-01'],
            // "between" without an upper bound is incomplete.
            ['field' => 'created_date', 'op' => 'between', 'value' => '2026-07-01'],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Alpha Campaign', 'Bravo Campaign']);
});

it('keeps rows when a fresh filter is seeded from the report window', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    // What the builder sends the moment you add a date filter: "between" spanning
    // the report's own since/until. Both rows were created inside that window, so
    // adding the filter must not blank the report.
    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'ad',
        'date_filters' => json_encode([
            [
                'field' => 'created_date',
                'op' => 'between',
                'value' => '2026-07-01',
                'value2' => '2026-07-31',
            ],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Alpha Ad', 'Bravo Ad']);
});

it('tolerates a reversed between range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedReportDates($workspace);

    $response = $this->getJson(reportDataUrl($workspace, [
        'group_by' => 'campaign',
        'date_filters' => json_encode([
            [
                'field' => 'created_date',
                'op' => 'between',
                'value' => '2026-07-12',
                'value2' => '2026-07-08',
            ],
        ]),
    ]))->assertOk();

    expect(rowNames($response))->toBe(['Bravo Campaign']);
});
