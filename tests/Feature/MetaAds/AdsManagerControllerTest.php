<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Build a minimal Meta Ads graph for a workspace: a connected meta user, two
 * ad accounts, and one ad per account that happen to share the same name so the
 * `ad_name` grouping has something to merge across accounts.
 */
function seedAdsManager($workspace): array
{
    $metaUser = MetaUser::create(['id' => 9001, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $accountA = AdAccount::create(['id' => 101, 'name' => 'Account A']);
    $accountB = AdAccount::create(['id' => 102, 'name' => 'Account B']);
    $metaUser->adAccounts()->attach([$accountA->id, $accountB->id]);

    $today = Carbon::today()->toDateString();

    $build = function (AdAccount $account, int $base, string $adName, float $spend) use ($today) {
        $campaign = Campaign::create(['id' => $base, 'meta_ads_account_id' => $account->id, 'name' => "Campaign {$base}"]);
        $set = AdSet::create(['id' => $base + 1, 'meta_ads_account_id' => $account->id, 'meta_ads_campaign_id' => $campaign->id, 'name' => "Ad Set {$base}"]);
        $ad = Ad::create(['id' => $base + 2, 'meta_ads_account_id' => $account->id, 'meta_ads_campaign_id' => $campaign->id, 'meta_ads_set_id' => $set->id, 'name' => $adName]);

        Insight::create([
            'meta_ads_ad_id' => $ad->id,
            'date' => $today,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_ads_set_id' => $set->id,
            'spend' => $spend,
            'impressions' => 1000,
            'clicks' => 50,
        ]);

        return compact('campaign', 'set', 'ad');
    };

    $build($accountA, 1000, 'Shared Creative', 100);
    $build($accountB, 2000, 'Shared Creative', 40);

    return ['metaUser' => $metaUser, 'accountA' => $accountA, 'accountB' => $accountB];
}

function adsManagerUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager', ['workspace' => $workspace, ...$query]);
}

it('renders the unified ads manager and defaults to grouping by ad name', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/index')
            ->where('query.groupBy', 'ad_name')
            ->has('accounts', 2)
            ->has('selectedAccounts', 2)
            // Same ad name across both accounts collapses to a single row...
            ->has('rows.data', 1)
            // ...with spend summed across accounts (100 + 40).
            ->where('rows.data.0.name', 'Shared Creative')
            ->where('rows.data.0.spend', fn ($spend) => (float) $spend === 140.0)
        );
});

it('groups by account into one row per account', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace, ['group_by' => 'account']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('query.groupBy', 'account')
            ->has('rows.data', 2)
        );
});

it('supports every group_by dimension', function (string $groupBy, int $expectedRows) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace, ['group_by' => $groupBy]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('query.groupBy', $groupBy)
            ->has('rows.data', $expectedRows)
        );
})->with([
    'ad_name' => ['ad_name', 1],
    'ad' => ['ad', 2],
    'campaign' => ['campaign', 2],
    'ad_set' => ['ad_set', 2],
    'account' => ['account', 2],
]);

it('restricts aggregation to the selected accounts', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace, ['accounts' => [(string) $ctx['accountA']->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectedAccounts', 1)
            ->has('rows.data', 1)
            // Only Account A's spend remains.
            ->where('rows.data.0.spend', fn ($spend) => (float) $spend === 100.0)
        );
});

it('applies metric filters as HAVING on the aggregated totals', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // Grouped by ad, Account B's ad totals 40 spend and is filtered out by spend > 50.
    $filters = json_encode([['field' => 'spend', 'op' => 'gt', 'value' => 50]]);

    $this->get(adsManagerUrl($workspace, ['group_by' => 'ad', 'metric_filters' => $filters]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)
            ->where('rows.data.0.spend', fn ($spend) => (float) $spend === 100.0)
        );
});

it('sorts by a computed metric server-side', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // Grouped by ad: A has cpc 100/50 = 2.0, B has cpc 40/50 = 0.8. Ascending cpc
    // puts B (spend 40) first, proving the derived metric sorts on the server
    // rather than defaulting to -spend (which would put A first).
    $this->get(adsManagerUrl($workspace, ['group_by' => 'ad', 'sort' => 'cpc']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)
            ->where('rows.data.0.spend', fn ($spend) => (float) $spend === 40.0)
            ->where('rows.data.1.spend', fn ($spend) => (float) $spend === 100.0)
        );
});

it('shows entities with no insights in the date range (zeroed metrics)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    // seedAdsManager writes insights dated today; query a window with none.
    seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace, [
        'group_by' => 'campaign',
        'since' => '2020-01-01',
        'until' => '2020-01-07',
    ]))
        ->assertInertia(fn (Assert $page) => $page
            // Both campaigns still appear even though neither has insights here...
            ->has('rows.data', 2)
            // ...with metrics folded to zero.
            ->where('rows.data.0.spend', fn ($spend) => (float) $spend === 0.0)
            ->where('rows.data.1.spend', fn ($spend) => (float) $spend === 0.0)
        );
});

it('includes an ad count per group, except when grouping by ad id', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);
    // A second ad in account A's campaign/ad set so the count exceeds one.
    Ad::create([
        'id' => 1003,
        'meta_ads_account_id' => 101,
        'meta_ads_campaign_id' => 1000,
        'meta_ads_set_id' => 1001,
        'name' => 'Second Ad',
    ]);

    // Ad Name: "Shared Creative" exists in both accounts → 2 ads.
    $this->get(adsManagerUrl($workspace, ['group_by' => 'ad_name', 'filter' => ['search' => 'Shared']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.name', 'Shared Creative')
            ->where('rows.data.0.ads_count', fn ($n) => (int) $n === 2)
        );

    // Campaign 1000 now has 2 ads; default -spend sort puts it first (spend 100).
    $this->get(adsManagerUrl($workspace, ['group_by' => 'campaign']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.ads_count', fn ($n) => (int) $n === 2)
        );

    // Grouping by ad id carries no ad-count column.
    $this->get(adsManagerUrl($workspace, ['group_by' => 'ad']))
        ->assertInertia(fn (Assert $page) => $page->missing('rows.data.0.ads_count'));
});

it('returns the meta ad-preview iframe src for an ad', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace); // ad 1002 belongs to account 101

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'body' => '<iframe src="https://business.facebook.com/preview?d=TOKEN&amp;t=1" width="320" height="570"></iframe>',
            ]],
        ], 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        // &amp; is decoded back to & for a usable src.
        ->assertJson(['src' => 'https://business.facebook.com/preview?d=TOKEN&t=1']);
});

it('does not expose ad previews for ads outside the workspace accounts', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // An ad on an account this workspace cannot see.
    $foreign = AdAccount::create(['id' => 999, 'name' => 'Foreign']);
    Ad::create(['id' => 9002, 'meta_ads_account_id' => $foreign->id, 'meta_ads_campaign_id' => 1, 'meta_ads_set_id' => 1, 'name' => 'Foreign Ad']);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 9002]))
        ->assertNotFound();
});

it('returns ad detail (dimensions + preview) for the drawer', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'body' => '<iframe src="https://business.facebook.com/p?d=TOK"></iframe>',
            ]],
        ], 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.detail', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('dimensions.ad_id', '1002')
        ->assertJsonPath('dimensions.ad_name', 'Shared Creative')
        ->assertJsonPath('dimensions.campaign_name', 'Campaign 1000')
        ->assertJsonPath('dimensions.adset_name', 'Ad Set 1000')
        ->assertJsonPath('dimensions.account_name', 'Account A')
        ->assertJsonPath('dimensions.ad_type', 'Image') // seed has no creative/video_id
        ->assertJsonMissingPath('scores')
        ->assertJsonPath('preview.src', 'https://business.facebook.com/p?d=TOK');
});

it('forbids non-members', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(adsManagerUrl($workspace))
        ->assertForbidden();
});
