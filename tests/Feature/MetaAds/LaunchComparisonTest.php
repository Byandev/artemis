<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Jobs\SyncAds;
use Modules\MetaAds\Jobs\SyncAdSets;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * An account the workspace can see, holding one campaign → ad set → ad per
 * launch. Each launch is [id, start, spend-by-date]; the insights sit on the
 * launch's single ad so every level sums to the same numbers.
 *
 * @param  array<int, array{id: int, start: string, insights?: array<string, float>}>  $launches
 */
function seedLaunches($workspace, array $launches): AdAccount
{
    $metaUser = MetaUser::create(['id' => 9301, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 701, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    foreach ($launches as $launch) {
        $id = $launch['id'];

        Campaign::create([
            'id' => $id,
            'meta_ads_account_id' => $account->id,
            'name' => "Campaign {$id}",
            'start_time' => $launch['start'],
        ]);
        AdSet::create([
            'id' => $id + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $id,
            'name' => "Ad Set {$id}",
            'start_time' => $launch['start'],
        ]);
        Ad::create([
            'id' => $id + 2,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $id,
            'meta_ads_set_id' => $id + 1,
            'name' => "Ad {$id}",
            'start_time' => $launch['start'],
        ]);

        foreach ($launch['insights'] ?? [] as $date => $spend) {
            Insight::create([
                'meta_ads_ad_id' => $id + 2,
                'date' => $date,
                'meta_ads_account_id' => $account->id,
                'meta_ads_campaign_id' => $id,
                'meta_ads_set_id' => $id + 1,
                'spend' => $spend,
                'impressions' => (int) ($spend * 10),
            ]);
        }
    }

    return $account;
}

function launchComparisonUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.launch-comparison', ['workspace' => $workspace, ...$query]);
}

/** Spend per day slot, null for a blank (future) day. */
function spendByDay(array $item): array
{
    return array_map(fn ($p) => $p === null ? null : (float) $p['spend'], $item['points']);
}

it('lines launches up by day number instead of calendar date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 09:00:00', 'insights' => [
            '2026-08-31' => 99, // before launch — not part of Day 1..N
            '2026-09-01' => 10,
            '2026-09-02' => 20,
            '2026-09-03' => 30,
        ]],
        ['id' => 2000, 'start' => '2026-09-02 18:30:00', 'insights' => [
            '2026-09-02' => 5,
            // 09-03 missing: a past day with no delivery reads 0, not blank.
            '2026-09-04' => 15,
        ]],
    ]);

    $items = $this->get(launchComparisonUrl($workspace, ['days' => 3]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('workspaces/integrations/meta-ads/launch-comparison'))
        ->inertiaProps('items');

    // Newest launch first; each one's Day 1 is its own start date.
    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe('2000')
        ->and($items[0]['start_date'])->toBe('2026-09-02')
        ->and(spendByDay($items[0]))->toBe([5.0, 0.0, 15.0])
        ->and($items[0]['points'][0]['date'])->toBe('2026-09-02')
        ->and((float) $items[0]['points'][2]['impressions'])->toBe(150.0)
        ->and($items[1]['id'])->toBe('1000')
        ->and($items[1]['start_date'])->toBe('2026-09-01')
        ->and(spendByDay($items[1]))->toBe([10.0, 20.0, 30.0]);
});

it('leaves days that have not happened yet blank rather than zero', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    // Day 1 is two days ago, Day 3 is today, Day 4+ are future.
    seedLaunches($workspace, [
        // Day 2 had no delivery (a real 0); today hasn't synced yet (blank).
        ['id' => 1000, 'start' => '2026-09-08 08:00:00', 'insights' => ['2026-09-08' => 12]],
        // Today has synced, so it shows.
        ['id' => 2000, 'start' => '2026-09-08 08:00:00', 'insights' => ['2026-09-08' => 4, '2026-09-10' => 6]],
    ]);

    $items = collect($this->get(launchComparisonUrl($workspace, ['days' => 5]))
        ->assertOk()
        ->inertiaProps('items'))->keyBy('id');

    expect(spendByDay($items['1000']))->toBe([12.0, 0.0, null, null, null])
        ->and(spendByDay($items['2000']))->toBe([4.0, 0.0, 6.0, null, null]);
});

it('compares ad sets and ads keyed by their own start time', function (string $level, string $offset) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 00:00:00', 'insights' => ['2026-09-01' => 7, '2026-09-02' => 8]],
        ['id' => 2000, 'start' => '2026-09-05 00:00:00', 'insights' => ['2026-09-05' => 3]],
    ]);

    $ids = [(string) (1000 + (int) $offset), (string) (2000 + (int) $offset)];

    $items = $this->get(launchComparisonUrl($workspace, ['level' => $level, 'days' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('level', $level))
        ->inertiaProps('items');

    expect(array_column($items, 'id'))->toBe(array_reverse($ids))
        ->and(spendByDay($items[0]))->toBe([3.0, 0.0])
        ->and(spendByDay($items[1]))->toBe([7.0, 8.0]);
})->with([
    'ad set' => ['ad_set', '1'],
    'ad' => ['ad', '2'],
]);

it('shows every launch, newest first', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 00:00:00', 'insights' => ['2026-09-01' => 5]],
        ['id' => 2000, 'start' => '2026-09-03 00:00:00', 'insights' => ['2026-09-03' => 5]],
        // Never delivered — still a launch, so still listed.
        ['id' => 3000, 'start' => '2026-09-05 00:00:00'],
        // Scheduled for the future — hasn't launched, so it isn't listed.
        ['id' => 4000, 'start' => '2026-09-20 00:00:00'],
    ]);

    $props = $this->get(launchComparisonUrl($workspace))->assertOk()->inertiaProps();

    expect(array_column($props['items'], 'id'))->toBe(['3000', '2000', '1000'])
        ->and($props['pagination']['total'])->toBe(3);
});

it('filters launches by start date range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 08:00:00'],
        ['id' => 2000, 'start' => '2026-09-03 23:30:00'], // late on the last day — still in
        ['id' => 3000, 'start' => '2026-09-05 00:00:00'],
    ]);

    $ids = fn (array $query) => array_column(
        $this->get(launchComparisonUrl($workspace, $query))->assertOk()->inertiaProps('items'),
        'id',
    );

    // Both ends inclusive.
    expect($ids(['start_from' => '2026-09-01', 'start_to' => '2026-09-03']))->toBe(['2000', '1000'])
        // Either end may be left open.
        ->and($ids(['start_from' => '2026-09-03']))->toBe(['3000', '2000'])
        ->and($ids(['start_to' => '2026-09-02']))->toBe(['1000'])
        // A reversed pair is swapped, not empty.
        ->and($ids(['start_from' => '2026-09-03', 'start_to' => '2026-09-01']))->toBe(['2000', '1000'])
        // Junk is ignored.
        ->and($ids(['start_from' => 'yesterday']))->toBe(['3000', '2000', '1000']);

    $this->get(launchComparisonUrl($workspace, ['start_from' => '2026-09-01', 'start_to' => '2026-09-03']))
        ->assertInertia(fn ($page) => $page
            ->where('startRange.from', '2026-09-01')
            ->where('startRange.to', '2026-09-03'));
});

it('applies the Ads Manager filters', function () {
    ['workspace' => $workspace, 'user' => $user] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 00:00:00', 'insights' => [
            '2026-09-01' => 100,
            '2026-09-02' => 100,
            '2026-09-09' => 5000, // Day 9 — outside a 3-day window
        ]],
        ['id' => 2000, 'start' => '2026-09-03 00:00:00', 'insights' => ['2026-09-03' => 900]],
        ['id' => 3000, 'start' => '2026-09-05 00:00:00'], // never delivered
    ]);

    Campaign::whereKey(1000)->update(['objective' => 'OUTCOME_SALES', 'name' => 'Sale Broad']);
    Campaign::whereKey(2000)->update(['objective' => 'OUTCOME_LEADS', 'created_time' => '2026-08-20 10:00:00']);
    Ad::whereKey(1002)->update(['creator_id' => $user->id]);

    $props = fn (array $query) => $this->get(launchComparisonUrl($workspace, ['days' => 3, ...$query]))
        ->assertOk()
        ->inertiaProps();
    $ids = fn (array $query) => array_column($props($query)['items'], 'id');
    $metric = fn (array $rows) => ['metric_filters' => json_encode($rows)];

    // Name search.
    expect($ids(['search' => 'sale']))->toBe(['1000'])
        // Objective rides in metric_filters, like on the Ads Manager.
        ->and($ids($metric([['field' => 'campaign_objective', 'op' => 'is', 'value' => 'OUTCOME_LEADS']])))->toBe(['2000'])
        ->and($ids($metric([['field' => 'campaign_objective', 'op' => 'is_not', 'value' => 'OUTCOME_LEADS']])))->toBe(['3000', '1000'])
        // Lifecycle dates.
        ->and($ids(['date_filters' => json_encode([['field' => 'created_date', 'op' => 'on', 'value' => '2026-08-20']])]))->toBe(['2000'])
        ->and($ids(['date_filters' => json_encode([['field' => 'started_date', 'op' => 'after', 'value' => '2026-09-02']])]))->toBe(['3000', '2000'])
        // Metrics test Day 1..N only: campaign 1000 spent 200 in its first 3
        // days (its 5000 on Day 9 doesn't count).
        ->and($ids($metric([['field' => 'spend', 'op' => 'gt', 'value' => 500]])))->toBe(['2000'])
        ->and($ids($metric([['field' => 'spend', 'op' => 'range', 'value' => 150, 'value2' => 250]])))->toBe(['1000'])
        // An item that never delivered still tests as 0.
        ->and($ids($metric([['field' => 'spend', 'op' => 'eq', 'value' => 0]])))->toBe(['3000'])
        // Creator is ad-level: it narrows ads, and is ignored for campaigns.
        ->and($ids(['level' => 'ad', 'creator_id' => (string) $user->id]))->toBe(['1002'])
        ->and($ids(['level' => 'ad', 'creator_id' => 'unassigned']))->toBe(['3002', '2002'])
        ->and($ids(['creator_id' => (string) $user->id]))->toBe(['3000', '2000', '1000']);

    // The pager counts the filtered set, and the filters come back for a refresh.
    $filtered = $props([...$metric([['field' => 'spend', 'op' => 'gte', 'value' => 0]]), 'search' => 'Campaign']);

    expect($filtered['pagination']['total'])->toBe(2)
        ->and($filtered['filters']['search'])->toBe('Campaign')
        ->and($filtered['filters']['metric'][0])->toMatchArray(['field' => 'spend', 'op' => 'gte']);
});

it('pages through launches instead of capping them', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    // Twelve launches a day apart: 1000 is the oldest, 12000 the newest.
    seedLaunches($workspace, array_map(fn ($n) => [
        'id' => $n * 1000,
        'start' => sprintf('2026-08-%02d 00:00:00', $n),
    ], range(1, 12)));

    $first = $this->get(launchComparisonUrl($workspace))->assertOk()->inertiaProps();

    expect($first['items'])->toHaveCount(10)
        ->and($first['items'][0]['id'])->toBe('12000')
        ->and($first['pagination'])->toMatchArray(['currentPage' => 1, 'lastPage' => 2, 'perPage' => 10, 'total' => 12]);

    $second = $this->get(launchComparisonUrl($workspace, ['page' => 2]))->assertOk()->inertiaProps();

    expect(array_column($second['items'], 'id'))->toBe(['2000', '1000']);

    // A bigger page fits them all; an unsupported size falls back to 10.
    expect($this->get(launchComparisonUrl($workspace, ['per_page' => 25]))->inertiaProps('items'))->toHaveCount(12)
        ->and($this->get(launchComparisonUrl($workspace, ['per_page' => 3]))->inertiaProps('pagination.perPage'))->toBe(10);
});

it('shows nothing when every account is unticked', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 00:00:00', 'insights' => ['2026-09-01' => 5]],
    ]);

    // The page sends this sentinel for an empty selection; no accounts param
    // at all would mean "all".
    $props = $this->get(launchComparisonUrl($workspace, ['accounts' => ['none']]))->assertOk()->inertiaProps();

    expect($props['items'])->toBe([])
        ->and($props['selectedAccounts'])->toBe([]);
});

it('leaves out launches from accounts outside the workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    seedLaunches($workspace, [
        ['id' => 1000, 'start' => '2026-09-01 00:00:00', 'insights' => ['2026-09-01' => 5]],
    ]);

    // A campaign on an account this workspace isn't connected to.
    $foreign = AdAccount::create(['id' => 702, 'name' => 'Someone else']);
    Campaign::create([
        'id' => 5000,
        'meta_ads_account_id' => $foreign->id,
        'name' => 'Foreign',
        'start_time' => '2026-09-01 00:00:00',
    ]);

    $items = $this->get(launchComparisonUrl($workspace))
        ->assertOk()
        ->inertiaProps('items');

    expect(array_column($items, 'id'))->toBe(['1000']);
});

/** Graph responses per edge; /adsets first since /ads* would also match it. */
function launchFakeGraph(array $ads, array $adSets = []): void
{
    Http::fake([
        'graph.facebook.com/*/adsets*' => Http::response(['data' => $adSets], 200),
        'graph.facebook.com/*/ads*' => Http::response([
            'data' => $ads,
            'paging' => ['cursors' => ['after' => 'A']],
        ], 200),
    ]);
}

function launchSyncAccount(array $sets): AdAccount
{
    $metaUser = MetaUser::create(['id' => 7301, 'name' => 'Token Owner', 'access_token' => 'fake-token']);
    $account = AdAccount::create(['id' => 556000333, 'name' => 'Sync Account']);
    $metaUser->adAccounts()->attach($account->id);

    Campaign::create(['id' => 111, 'meta_ads_account_id' => $account->id, 'name' => 'Campaign']);
    foreach ($sets as $id => $start) {
        AdSet::create([
            'id' => $id,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => 111,
            'name' => "Set {$id}",
            'start_time' => $start,
        ]);
    }

    return $account;
}

function launchAdRow(string $id, string $setId, ?string $created): array
{
    return array_filter([
        'id' => $id,
        'name' => "Ad {$id}",
        'adset_id' => $setId,
        'campaign_id' => '111',
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
        'created_time' => $created,
    ], fn ($v) => $v !== null);
}

describe('ad start time', function () {
    it('is the later of the ad created time and its ad set start time', function () {
        $account = launchSyncAccount([
            222 => '2026-09-01 08:00:00',
            333 => '2026-09-06 08:00:00', // scheduled after the ad was made
            444 => null,                  // ad set with no start time
        ]);

        launchFakeGraph([
            launchAdRow('900001', '222', '2026-09-03 10:00:00'),
            launchAdRow('900002', '333', '2026-09-03 10:00:00'),
            launchAdRow('900003', '444', '2026-09-04 10:00:00'),
            // No created time: falls back to the ad set's start.
            launchAdRow('900004', '222', null),
        ]);

        (new SyncAds($account))->handle();

        expect(Ad::find(900001)->start_time->toDateTimeString())->toBe('2026-09-03 10:00:00')
            ->and(Ad::find(900002)->start_time->toDateTimeString())->toBe('2026-09-06 08:00:00')
            ->and(Ad::find(900003)->start_time->toDateTimeString())->toBe('2026-09-04 10:00:00')
            ->and(Ad::find(900004)->start_time->toDateTimeString())->toBe('2026-09-01 08:00:00');
    });

    it('follows an ad set that is rescheduled after the ad synced', function () {
        $account = launchSyncAccount([222 => '2026-09-01 08:00:00']);

        launchFakeGraph(
            [launchAdRow('900001', '222', '2026-09-03 10:00:00')],
            [['id' => '222', 'campaign_id' => '111', 'name' => 'Set 222', 'start_time' => '2026-09-08 00:00:00']],
        );

        (new SyncAds($account))->handle();
        expect(Ad::find(900001)->start_time->toDateTimeString())->toBe('2026-09-03 10:00:00');

        // The ad didn't change, so only the ad set sync sees the new schedule.
        (new SyncAdSets($account))->handle();
        expect(Ad::find(900001)->start_time->toDateTimeString())->toBe('2026-09-08 00:00:00');
    });

    it('backfills every ad when run without a scope', function () {
        $account = launchSyncAccount([222 => '2026-09-05 00:00:00']);

        Ad::create([
            'id' => 900009,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => 111,
            'meta_ads_set_id' => 222,
            'name' => 'Pre-existing ad',
            'created_time' => '2026-09-02 00:00:00',
        ]);

        expect(Ad::find(900009)->start_time)->toBeNull();

        Ad::refreshStartTimes();

        expect(Ad::find(900009)->start_time->toDateTimeString())->toBe('2026-09-05 00:00:00');
    });
});
