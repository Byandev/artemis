<?php

use App\Models\User;
use Illuminate\Support\Carbon;
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

it('forbids non-members', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(adsManagerUrl($workspace))
        ->assertForbidden();
});
