<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Creative;
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

function dataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

it('renders the ads manager shell (accounts + initial query, no rows)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $this->get(adsManagerUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/index')
            ->where('query.groupBy', 'ad_name')
            ->has('accounts', 2)
            ->has('selectedAccounts', 2)
            // Grid rows are loaded client-side from the data() API now.
            ->missing('rows')
        );
});

it('aggregates rows for the default ad_name grouping via the data API', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $data = $this->getJson(dataUrl($workspace))->assertOk()->json('rows.data');

    // Same ad name across both accounts collapses to one row, spend summed (100 + 40).
    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Shared Creative')
        ->and((float) $data[0]['spend'])->toBe(140.0);
});

it('supports every group_by dimension', function (string $groupBy, int $expectedRows) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $data = $this->getJson(dataUrl($workspace, ['group_by' => $groupBy]))->assertOk()->json('rows.data');

    expect($data)->toHaveCount($expectedRows);
})->with([
    'ad_name' => ['ad_name', 1],
    'ad' => ['ad', 2],
    'campaign' => ['campaign', 2],
    'ad_set' => ['ad_set', 2],
    'account' => ['account', 2],
    // Neither seeded ad set promotes a page, so both land in one bucket.
    'page' => ['page', 1],
]);

it('restricts aggregation to the selected accounts', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsManager($workspace);

    $data = $this->getJson(dataUrl($workspace, ['accounts' => [(string) $ctx['accountA']->id]]))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and((float) $data[0]['spend'])->toBe(100.0); // only Account A
});

it('applies metric filters as HAVING on the aggregated totals', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // Grouped by ad, Account B's ad totals 40 spend and is filtered out by spend > 50.
    $filters = json_encode([['field' => 'spend', 'op' => 'gt', 'value' => 50]]);

    $data = $this->getJson(dataUrl($workspace, ['group_by' => 'ad', 'metric_filters' => $filters]))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(1)
        ->and((float) $data[0]['spend'])->toBe(100.0);
});

it('sorts by a computed metric server-side', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // Grouped by ad: A has cpc 2.0, B has cpc 0.8. Ascending cpc puts B (spend 40)
    // first, proving the derived metric sorts server-side (not default -spend).
    $data = $this->getJson(dataUrl($workspace, ['group_by' => 'ad', 'sort' => 'cpc']))
        ->assertOk()->json('rows.data');

    expect($data)->toHaveCount(2)
        ->and((float) $data[0]['spend'])->toBe(40.0)
        ->and((float) $data[1]['spend'])->toBe(100.0);
});

it('shows entities with no insights in the date range (zeroed metrics)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    // seedAdsManager writes insights dated today; query a window with none.
    seedAdsManager($workspace);

    $data = $this->getJson(dataUrl($workspace, [
        'group_by' => 'campaign',
        'since' => '2020-01-01',
        'until' => '2020-01-07',
    ]))->assertOk()->json('rows.data');

    // Both campaigns still appear with zeroed metrics.
    expect($data)->toHaveCount(2)
        ->and((float) $data[0]['spend'])->toBe(0.0)
        ->and((float) $data[1]['spend'])->toBe(0.0);
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
    $adName = $this->getJson(dataUrl($workspace, ['group_by' => 'ad_name', 'filter' => ['search' => 'Shared']]))
        ->assertOk()->json('rows.data');
    expect($adName[0]['name'])->toBe('Shared Creative')
        ->and((int) $adName[0]['ads_count'])->toBe(2);

    // Campaign 1000 has 2 ads; default -spend sort puts it first (spend 100).
    $campaign = $this->getJson(dataUrl($workspace, ['group_by' => 'campaign']))->assertOk()->json('rows.data');
    expect((int) $campaign[0]['ads_count'])->toBe(2);

    // Grouping by ad id carries no ad-count column.
    $ads = $this->getJson(dataUrl($workspace, ['group_by' => 'ad']))->assertOk()->json('rows.data');
    expect($ads[0])->not->toHaveKey('ads_count');
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
        // The frame itself is fetched server-side to confirm it rendered.
        'business.facebook.com/*' => Http::response('<div>the ad</div>', 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        // &amp; is decoded back to & for a usable src.
        ->assertJson(['src' => 'https://business.facebook.com/preview?d=TOKEN&t=1'])
        ->assertJsonPath('format', 'MOBILE_FEED_STANDARD')
        ->assertJsonPath('reason', null);
});

it('falls back to another ad format when meta renders "Story Unavailable"', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    Http::fake([
        // Meta hands back a frame for every format; only the URL differs.
        'graph.facebook.com/*' => function ($request) {
            $format = $request->data()['ad_format'] ?? 'unknown';

            return Http::response([
                'data' => [['body' => '<iframe src="https://business.facebook.com/p?f='.$format.'"></iframe>']],
            ], 200);
        },
        // The feed frame is the "Story Unavailable" interstitial; Instagram renders.
        // (Matches the frame URL's `?f=`, not the Graph call's `ad_format=`.)
        '*f=MOBILE_FEED_STANDARD*' => Http::response(
            '<html><body><h2>Story Unavailable</h2><p>The story in this ad is unavailable.</p></body></html>',
            200,
        ),
        'business.facebook.com/*' => Http::response('<div>the ad</div>', 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('format', 'INSTAGRAM_STANDARD')
        ->assertJsonPath('requested_format', 'MOBILE_FEED_STANDARD')
        ->assertJsonPath('src', 'https://business.facebook.com/p?f=INSTAGRAM_STANDARD')
        ->assertJsonPath('reason', null);
});

it('retries through the account\'s other linked tokens before giving up', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace); // meta user 9001 (token "test-token") owns account 101

    // A second person connected to the same ad account. Meta renders previews
    // with the signer's permissions, so this one having a role on the owning
    // page is what makes the ad previewable at all.
    $second = MetaUser::create(['id' => 9002, 'name' => 'Page Admin', 'access_token' => 'second-token']);
    $second->workspaces()->attach($workspace->id);
    $second->adAccounts()->attach(101);

    Http::fake([
        // Echo the signing token back in the frame URL so the stubs below can
        // tell the two apart.
        'graph.facebook.com/*' => function ($request) {
            $token = str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '');

            return Http::response([
                'data' => [['body' => '<iframe src="https://business.facebook.com/p?tok='.$token.'"></iframe>']],
            ], 200);
        },
        // The original token has no page role: every format is unavailable to it.
        '*tok=test-token*' => Http::response('<h2>Story Unavailable</h2>', 200),
        '*tok=second-token*' => Http::response('<div>the ad</div>', 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('src', 'https://business.facebook.com/p?tok=second-token')
        ->assertJsonPath('reason', null);
});

it('reports story_unavailable when no ad format renders', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [['body' => '<iframe src="https://business.facebook.com/p?d=TOK"></iframe>']],
        ], 200),
        'business.facebook.com/*' => Http::response(
            '<html><body><h2>Story Unavailable</h2></body></html>',
            200,
        ),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.preview', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('src', null)
        ->assertJsonPath('format', null)
        ->assertJsonPath('reason', 'story_unavailable');
});

it('keeps returning ad detail when the preview call fails outright', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Unsupported get request', 'code' => 100]], 400),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.detail', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('dimensions.ad_name', 'Shared Creative')
        ->assertJsonPath('preview.src', null)
        ->assertJsonPath('preview.reason', 'no_preview')
        ->assertJsonPath('preview.fallback.ads_manager_url', 'https://adsmanager.facebook.com/adsmanager/manage/ads?act=101&selected_ad_ids=1002');
});

it('offers ads library and instagram links when the preview cannot be rendered', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // Ad 1002's creative, carrying the page it ran under and its IG permalink.
    $creative = Creative::create([
        'id' => 7001,
        'meta_ads_account_id' => 101,
        'meta_page_id' => 555000111,
        'title' => 'Headline',
        'body' => 'Ad copy',
        'thumbnail_url' => 'https://scontent.example/thumb.jpg',
        'instagram_permalink_url' => 'https://www.instagram.com/p/ABC123/',
    ]);
    Ad::where('id', 1002)->update(['meta_ads_creative_id' => $creative->id]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [['body' => '<iframe src="https://business.facebook.com/p?d=TOK"></iframe>']],
        ], 200),
        'business.facebook.com/*' => Http::response('<h2>Story Unavailable</h2>', 200),
    ]);

    $response = $this->getJson(route('workspaces.metaads.ads-manager.detail', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('preview.src', null)
        ->assertJsonPath('preview.reason', 'story_unavailable')
        ->assertJsonPath('preview.fallback.image_url', 'https://scontent.example/thumb.jpg')
        ->assertJsonPath('preview.fallback.instagram_url', 'https://www.instagram.com/p/ABC123/');

    // Scoped to the page, since Meta's Library IDs aren't the ad ids we hold,
    // and pre-filtered by the ad's own copy so a busy page isn't a haystack.
    $library = $response->json('preview.fallback.ads_library_url');
    expect($library)->toContain('facebook.com/ads/library/')
        ->toContain('view_all_page_id=555000111')
        ->toContain('search_type=page')
        ->toContain('active_status=all')   // stopped ads are listed too
        ->toContain('q=Ad+copy');
});

it('keeps the ads library keyword short enough not to over-constrain', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    $creative = Creative::create([
        'id' => 7002,
        'meta_ads_account_id' => 101,
        'meta_page_id' => 555000222,
        'body' => "Ten  words   of\nprimary text here that should get cut off well before the end",
    ]);
    Ad::where('id', 1002)->update(['meta_ads_creative_id' => $creative->id]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => [['body' => '<iframe src="https://business.facebook.com/p?d=T"></iframe>']]], 200),
        'business.facebook.com/*' => Http::response('<h2>Story Unavailable</h2>', 200),
    ]);

    $library = $this->getJson(route('workspaces.metaads.ads-manager.detail', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->json('preview.fallback.ads_library_url');

    // Six words, whitespace collapsed — the library matches keywords unordered,
    // so the whole body would land on an empty result page.
    expect($library)->toContain('q=Ten+words+of+primary+text+here')
        ->not->toContain('cut+off');
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
        'business.facebook.com/*' => Http::response('<div>the ad</div>', 200),
    ]);

    $this->getJson(route('workspaces.metaads.ads-manager.detail', ['workspace' => $workspace, 'ad' => 1002]))
        ->assertOk()
        ->assertJsonPath('dimensions.ad_id', '1002')
        ->assertJsonPath('dimensions.ad_name', 'Shared Creative')
        ->assertJsonPath('dimensions.campaign_name', 'Campaign 1000')
        ->assertJsonPath('dimensions.adset_name', 'Ad Set 1000')
        ->assertJsonPath('dimensions.account_name', 'Account A')
        ->assertJsonPath('dimensions.ad_type', 'Image') // seed has no creative/video_id
        ->assertJsonPath('dimensions.media_type', 'image')
        ->assertJsonMissingPath('scores')
        ->assertJsonPath('preview.src', 'https://business.facebook.com/p?d=TOK');
});

it('classifies ads as video or image via media_type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);

    // A VIDEO creative with NO top-level video_id is still a video.
    Creative::create(['id' => 700, 'meta_ads_account_id' => 101, 'object_type' => 'VIDEO']);
    Ad::create(['id' => 1500, 'meta_ads_account_id' => 101, 'meta_ads_campaign_id' => 1000, 'meta_ads_set_id' => 1001, 'name' => 'Vid Ad', 'meta_ads_creative_id' => 700]);
    // A PHOTO creative is an image.
    Creative::create(['id' => 701, 'meta_ads_account_id' => 101, 'object_type' => 'PHOTO']);
    Ad::create(['id' => 1501, 'meta_ads_account_id' => 101, 'meta_ads_campaign_id' => 1000, 'meta_ads_set_id' => 1001, 'name' => 'Pic Ad', 'meta_ads_creative_id' => 701]);

    $data = $this->getJson(dataUrl($workspace, ['group_by' => 'ad', 'filter' => ['search' => ' Ad']]))
        ->assertOk()->json('rows.data');

    $byName = collect($data)->keyBy('name');
    expect($byName['Vid Ad']['media_type'])->toBe('video')
        ->and($byName['Pic Ad']['media_type'])->toBe('image');
});

it('lists paginated ads under a scoped group via the data API', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsManager($workspace);
    // Second ad in campaign 1000 / account A.
    Ad::create([
        'id' => 1003,
        'meta_ads_account_id' => 101,
        'meta_ads_campaign_id' => 1000,
        'meta_ads_set_id' => 1001,
        'name' => 'Second Ad',
    ]);

    // Campaign 1000 → its two ads.
    $this->getJson(dataUrl($workspace, ['scope_by' => 'campaign', 'scope' => 1000]))
        ->assertOk()
        ->assertJsonPath('rows.total', 2)
        ->assertJsonCount(2, 'rows.data');

    // Ad name "Shared Creative" spans both accounts → 2 ads.
    $this->getJson(dataUrl($workspace, ['scope_by' => 'ad_name', 'scope' => 'Shared Creative']))
        ->assertJsonPath('rows.total', 2);
});

it('forbids non-members', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(adsManagerUrl($workspace))
        ->assertForbidden();
});
