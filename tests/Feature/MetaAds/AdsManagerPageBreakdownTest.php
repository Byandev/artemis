<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * One ad per ad set, each ad set optionally promoting a page. A Pancake page's
 * primary key IS the Facebook page id, so `meta_page_id` is that page's id.
 *
 * @param  array<int, array{page: ?Page, spend: float}>  $ads
 */
function seedPageBreakdown($workspace, array $ads): AdAccount
{
    $metaUser = MetaUser::create(['id' => 9101, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 501, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $today = Carbon::today()->toDateString();
    $base = 5000;

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
            'meta_page_id' => $spec['page']?->id,
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

function pageDataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

it('groups ads by the page their ad set promotes', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page']);
    $beta = Page::factory()->forWorkspace($workspace)->create(['name' => 'Beta Page']);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 100],
        // Second ad on the same page: the two spends collapse into one row.
        ['page' => $alpha, 'spend' => 40],
        ['page' => $beta, 'spend' => 25],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, ['group_by' => 'page']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);

    // Default sort is -spend, so Alpha (140) leads.
    expect($data[0]['name'])->toBe('Alpha Page')
        ->and($data[0]['id'])->toBe((string) $alpha->id)
        ->and((float) $data[0]['spend'])->toBe(140.0)
        ->and($data[0]['ads_count'])->toBe(2)
        ->and($data[1]['name'])->toBe('Beta Page')
        ->and((float) $data[1]['spend'])->toBe(25.0)
        ->and($data[1]['ads_count'])->toBe(1);
});

it('collects ad sets with no page in an unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page']);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 10],
        ['page' => null, 'spend' => 90],
        ['page' => null, 'spend' => 5],
    ]);

    $data = collect($this->getJson(pageDataUrl($workspace, ['group_by' => 'page']))
        ->assertOk()->json('rows.data'))->keyBy('name');

    expect($data)->toHaveCount(2)
        ->and((float) $data['Unassigned page']['spend'])->toBe(95.0)
        ->and($data['Unassigned page']['ads_count'])->toBe(2)
        // "0" so the drill-down can pass the bucket back as a scope value.
        ->and($data['Unassigned page']['id'])->toBe('0')
        ->and((float) $data['Alpha Page']['spend'])->toBe(10.0);
});

it('treats a soft-deleted page as unassigned', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $gone = Page::factory()->forWorkspace($workspace)->create(['name' => 'Deleted Page']);
    seedPageBreakdown($workspace, [['page' => $gone, 'spend' => 30]]);
    $gone->delete();

    $data = $this->getJson(pageDataUrl($workspace, ['group_by' => 'page']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Unassigned page')
        ->and((float) $data[0]['spend'])->toBe(30.0);
});

it('searches the page breakdown by page name', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page']);
    $beta = Page::factory()->forWorkspace($workspace)->create(['name' => 'Beta Page']);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 100],
        ['page' => $beta, 'spend' => 25],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, [
        'group_by' => 'page',
        'filter' => ['search' => 'Beta'],
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Beta Page');
});

it('drills a page row down to its ads', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page']);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 100],
        ['page' => $alpha, 'spend' => 40],
        ['page' => null, 'spend' => 7],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, [
        'scope_by' => 'page',
        'scope' => (string) $alpha->id,
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->sum(fn ($row) => (float) $row['spend']))->toBe(140.0);

    // The unassigned bucket drills down too, on the id the grid showed.
    $unassigned = $this->getJson(pageDataUrl($workspace, [
        'scope_by' => 'page',
        'scope' => '0',
    ]))->assertOk()->json('rows.data');

    expect($unassigned)->toHaveCount(1)
        ->and((float) $unassigned[0]['spend'])->toBe(7.0);
});

it('filters the page breakdown by started date through the ad set', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page']);
    seedPageBreakdown($workspace, [['page' => $alpha, 'spend' => 100]]);

    // Ad sets start 3 days ago, so an "after yesterday" filter excludes them.
    $data = $this->getJson(pageDataUrl($workspace, [
        'group_by' => 'page',
        'date_filters' => json_encode([[
            'field' => 'started_date',
            'op' => 'after',
            'value' => Carbon::yesterday()->toDateString(),
        ]]),
    ]))->assertOk()->json('rows.data');

    expect($data)->toBeEmpty();
});

/* ── Page owner: the same ad → ad set → page chain, one join further ── */

it('groups ads by the owner of the page their ad set promotes', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $mara = User::factory()->create(['name' => 'Mara Owner']);
    $niko = User::factory()->create(['name' => 'Niko Owner']);

    // Two of Mara's pages: their spend collapses into a single owner row.
    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page', 'owner_id' => $mara->id]);
    $beta = Page::factory()->forWorkspace($workspace)->create(['name' => 'Beta Page', 'owner_id' => $mara->id]);
    $gamma = Page::factory()->forWorkspace($workspace)->create(['name' => 'Gamma Page', 'owner_id' => $niko->id]);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 100],
        ['page' => $beta, 'spend' => 40],
        ['page' => $gamma, 'spend' => 25],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, ['group_by' => 'page_owner']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2);

    // Default sort is -spend, so Mara (140 across two pages) leads.
    expect($data[0]['name'])->toBe('Mara Owner')
        ->and($data[0]['id'])->toBe((string) $mara->id)
        ->and((float) $data[0]['spend'])->toBe(140.0)
        ->and($data[0]['ads_count'])->toBe(2)
        ->and($data[1]['name'])->toBe('Niko Owner')
        ->and((float) $data[1]['spend'])->toBe(25.0)
        ->and($data[1]['ads_count'])->toBe(1);
});

it('collects ads with no resolvable page owner in an unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $mara = User::factory()->create(['name' => 'Mara Owner']);
    $alpha = Page::factory()->forWorkspace($workspace)->create(['name' => 'Alpha Page', 'owner_id' => $mara->id]);
    $gone = Page::factory()->forWorkspace($workspace)->create(['name' => 'Deleted Page', 'owner_id' => $mara->id]);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 10],
        // No promoted page at all.
        ['page' => null, 'spend' => 90],
        // Page deleted on our side, so its owner can't be resolved either.
        ['page' => $gone, 'spend' => 5],
    ]);

    $gone->delete();

    $data = collect($this->getJson(pageDataUrl($workspace, ['group_by' => 'page_owner']))
        ->assertOk()->json('rows.data'))->keyBy('name');

    expect($data)->toHaveCount(2)
        ->and((float) $data['Unassigned owner']['spend'])->toBe(95.0)
        ->and($data['Unassigned owner']['ads_count'])->toBe(2)
        // "0" so the drill-down can pass the bucket back as a scope value.
        ->and($data['Unassigned owner']['id'])->toBe('0')
        ->and((float) $data['Mara Owner']['spend'])->toBe(10.0);
});

it('searches the page-owner breakdown by owner name', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $mara = User::factory()->create(['name' => 'Mara Owner']);
    $niko = User::factory()->create(['name' => 'Niko Owner']);

    seedPageBreakdown($workspace, [
        ['page' => Page::factory()->forWorkspace($workspace)->create(['owner_id' => $mara->id]), 'spend' => 100],
        ['page' => Page::factory()->forWorkspace($workspace)->create(['owner_id' => $niko->id]), 'spend' => 25],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, [
        'group_by' => 'page_owner',
        'filter' => ['search' => 'Niko'],
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Niko Owner');
});

it('drills a page-owner row down to its ads', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $mara = User::factory()->create(['name' => 'Mara Owner']);
    $niko = User::factory()->create(['name' => 'Niko Owner']);

    $alpha = Page::factory()->forWorkspace($workspace)->create(['owner_id' => $mara->id]);
    $beta = Page::factory()->forWorkspace($workspace)->create(['owner_id' => $mara->id]);
    $gamma = Page::factory()->forWorkspace($workspace)->create(['owner_id' => $niko->id]);

    seedPageBreakdown($workspace, [
        ['page' => $alpha, 'spend' => 100],
        ['page' => $beta, 'spend' => 40],
        ['page' => $gamma, 'spend' => 25],
        ['page' => null, 'spend' => 7],
    ]);

    $data = $this->getJson(pageDataUrl($workspace, [
        'scope_by' => 'page_owner',
        'scope' => (string) $mara->id,
    ]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->sum(fn ($row) => (float) $row['spend']))->toBe(140.0);

    // The unassigned bucket drills down too, on the id the grid showed.
    $unassigned = $this->getJson(pageDataUrl($workspace, [
        'scope_by' => 'page_owner',
        'scope' => '0',
    ]))->assertOk()->json('rows.data');

    expect($unassigned)->toHaveCount(1)
        ->and((float) $unassigned[0]['spend'])->toBe(7.0);
});
