<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Seed a minimal Meta Ads graph for the calendar: a connected meta user, one ad
 * account, three FB pages (whose ids ARE the meta_page_id), and campaigns created
 * on specific days. A campaign's page is derived from its ad set's meta_page_id.
 * Returns the created page models for assertions.
 */
function seedAdsCalendar($workspace): array
{
    $metaUser = MetaUser::create(['id' => 8001, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 201, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    // A Pancake page's primary key is the FB page id (== meta_page_id).
    $pageAlpha = Page::factory()->forWorkspace($workspace)->create(['id' => 9101, 'name' => 'Alpha Page']);
    $pageBravo = Page::factory()->forWorkspace($workspace)->create(['id' => 9102, 'name' => 'Bravo Page']);
    // A workspace page with NO campaigns — must still appear in the filter list.
    Page::factory()->forWorkspace($workspace)->create(['id' => 9103, 'name' => 'Charlie Page']);

    $cid = 5000;
    $sid = 6000;
    // One campaign created on $createdTime, attributed to $pageId via a single ad
    // set (the ad set's own created date is irrelevant to the calendar).
    $makeCampaign = function (string $createdTime, ?int $pageId) use (&$cid, &$sid, $account) {
        $campaign = Campaign::create([
            'id' => $cid++,
            'meta_ads_account_id' => $account->id,
            'name' => 'Campaign '.$cid,
            'created_time' => $createdTime,
        ]);

        AdSet::create([
            'id' => $sid++,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$sid,
            'created_time' => $createdTime,
        ]);

        return $campaign;
    };

    // June 2026: Alpha gets 2 campaigns on the 10th + 1 on the 15th; Bravo 1 on the 10th.
    $makeCampaign('2026-06-10 09:00:00', $pageAlpha->id);
    $makeCampaign('2026-06-10 11:00:00', $pageAlpha->id);
    $makeCampaign('2026-06-10 12:00:00', $pageBravo->id);
    $makeCampaign('2026-06-15 08:00:00', $pageAlpha->id);
    // A campaign whose ad set has no known page → "Unassigned page" bucket.
    $makeCampaign('2026-06-15 10:00:00', null);
    // Outside the requested month — must be excluded.
    $makeCampaign('2026-05-30 10:00:00', $pageAlpha->id);

    return ['account' => $account, 'alpha' => $pageAlpha, 'bravo' => $pageBravo];
}

function adsCalendarUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-calendar', ['workspace' => $workspace, ...$query]);
}

it('renders the calendar with per-day, per-page ad set counts for the month', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsCalendar($workspace);

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/calendar')
            ->where('month', '2026-06')
            ->where('monthLabel', 'June 2026')
            ->where('selectedPages', [])
            // June 10: 3 total — Alpha (2) then Bravo (1), sorted by volume.
            ->where('days.2026-06-10.total', 3)
            ->where('days.2026-06-10.pages.0.name', 'Alpha Page')
            ->where('days.2026-06-10.pages.0.count', 2)
            ->where('days.2026-06-10.pages.1.name', 'Bravo Page')
            // June 15: 2 total — Alpha (1) + an unassigned page (1).
            ->where('days.2026-06-15.total', 2)
            ->where('days.2026-06-15.pages', fn ($pages) => collect($pages)
                ->pluck('name')
                ->contains('Unassigned page'))
            // Filter lists ALL workspace pages (incl. Charlie, which has no ad
            // sets) plus the Unassigned bucket (a page-less ad set exists).
            ->where('pageOptions', fn ($options) => collect($options)->pluck('name')->sort()->values()->all()
                === ['Alpha Page', 'Bravo Page', 'Charlie Page', 'Unassigned page'])
        );
});

it('filters the calendar to a single page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsCalendar($workspace);

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06', 'pages' => [(string) $ctx['alpha']->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPages', [(string) $ctx['alpha']->id])
            // Only Alpha's ad sets remain: 2 on the 10th, 1 on the 15th.
            ->where('days.2026-06-10.total', 2)
            ->where('days.2026-06-15.total', 1)
            ->where('days.2026-06-15.pages.0.name', 'Alpha Page')
        );
});

it('filters the calendar to multiple pages at once', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsCalendar($workspace);

    // Alpha + Bravo together: June 10 keeps all 3 (2 Alpha + 1 Bravo), but the
    // unassigned ad set on June 15 is excluded (only Alpha's 1 remains).
    $this->get(adsCalendarUrl($workspace, [
        'month' => '2026-06',
        'pages' => [(string) $ctx['alpha']->id, (string) $ctx['bravo']->id],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPages', [(string) $ctx['alpha']->id, (string) $ctx['bravo']->id])
            ->where('days.2026-06-10.total', 3)
            ->where('days.2026-06-15.total', 1)
        );
});

it('combines a page with the unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsCalendar($workspace);

    // Alpha + "none": June 15 keeps both Alpha's 1 and the unassigned 1.
    $this->get(adsCalendarUrl($workspace, [
        'month' => '2026-06',
        'pages' => [(string) $ctx['alpha']->id, 'none'],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-15.total', 2)
            ->where('days.2026-06-10.total', 2) // only Alpha's two (Bravo excluded)
        );
});

it('counts a campaign once per page regardless of ad set count', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedAdsCalendar($workspace);

    // One campaign on June 20 with THREE ad sets: two on Alpha, one on Bravo.
    $campaign = Campaign::create([
        'id' => 5500,
        'meta_ads_account_id' => $ctx['account']->id,
        'name' => 'Multi-page Campaign',
        'created_time' => '2026-06-20 09:00:00',
    ]);
    foreach ([[6500, 9101], [6501, 9101], [6502, 9102]] as [$setId, $pageId]) {
        AdSet::create([
            'id' => $setId,
            'meta_ads_account_id' => $ctx['account']->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$setId,
            'created_time' => '2026-06-20 09:00:00',
        ]);
    }

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Two ad sets on Alpha collapse to a single campaign; the campaign
            // also shows under Bravo → grand total of 2 (1 per page), not 3.
            ->where('days.2026-06-20.total', 2)
            ->where('days.2026-06-20.pages', fn ($pages) => collect($pages)->firstWhere('name', 'Alpha Page')['count'] === 1
                && collect($pages)->firstWhere('name', 'Bravo Page')['count'] === 1)
        );
});

it('filters to ad sets with no resolvable page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsCalendar($workspace);

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06', 'pages' => ['none']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPages', ['none'])
            // Only the single unassigned ad set on June 15 remains.
            ->where('days.2026-06-15.total', 1)
            ->where('days.2026-06-15.pages.0.name', 'Unassigned page')
            ->missing('days.2026-06-10')
        );
});

it('defaults to the current month and forbids paging into the future', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsCalendar($workspace);

    Carbon::setTestNow('2026-06-22');

    $this->get(adsCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-06')
            // Current month is the latest viewable — no "next".
            ->where('canGoNext', false)
        );

    Carbon::setTestNow();
});

it('excludes ad sets from accounts the workspace cannot see', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedAdsCalendar($workspace);

    // A foreign account + campaign the workspace has no access to.
    $foreign = AdAccount::create(['id' => 999, 'name' => 'Foreign']);
    $foreignCampaign = Campaign::create([
        'id' => 5999,
        'meta_ads_account_id' => $foreign->id,
        'name' => 'Foreign Campaign',
        'created_time' => '2026-06-10 09:00:00',
    ]);
    AdSet::create([
        'id' => 7777,
        'meta_ads_account_id' => $foreign->id,
        'meta_ads_campaign_id' => $foreignCampaign->id,
        'meta_page_id' => 9101,
        'name' => 'Foreign Ad Set',
        'created_time' => '2026-06-10 09:00:00',
    ]);

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06']))
        ->assertOk()
        // June 10 stays at 3 — the foreign account's campaign is not counted.
        ->assertInertia(fn (Assert $page) => $page->where('days.2026-06-10.total', 3));
});

it('forbids non-members', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(adsCalendarUrl($workspace))
        ->assertForbidden();
});
