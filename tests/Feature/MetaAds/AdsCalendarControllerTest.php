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
 * account, two FB pages (whose ids ARE the meta_page_id), and ad sets created on
 * specific days/pages. Returns the created page models for assertions.
 */
function seedAdsCalendar($workspace): array
{
    $metaUser = MetaUser::create(['id' => 8001, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 201, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $campaign = Campaign::create(['id' => 5000, 'meta_ads_account_id' => $account->id, 'name' => 'Campaign']);

    // A Pancake page's primary key is the FB page id (== meta_page_id).
    $pageAlpha = Page::factory()->forWorkspace($workspace)->create(['id' => 9101, 'name' => 'Alpha Page']);
    $pageBravo = Page::factory()->forWorkspace($workspace)->create(['id' => 9102, 'name' => 'Bravo Page']);
    // A workspace page with NO ad sets — must still appear in the filter list.
    Page::factory()->forWorkspace($workspace)->create(['id' => 9103, 'name' => 'Charlie Page']);

    $id = 6000;
    $makeSet = function (string $createdTime, ?int $pageId) use (&$id, $account, $campaign) {
        AdSet::create([
            'id' => $id++,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$id,
            'created_time' => $createdTime,
        ]);
    };

    // June 2026: Alpha gets 2 on the 10th + 1 on the 15th; Bravo gets 1 on the 10th.
    $makeSet('2026-06-10 09:00:00', $pageAlpha->id);
    $makeSet('2026-06-10 11:00:00', $pageAlpha->id);
    $makeSet('2026-06-10 12:00:00', $pageBravo->id);
    $makeSet('2026-06-15 08:00:00', $pageAlpha->id);
    // An ad set with no known page → "Unassigned page" bucket.
    $makeSet('2026-06-15 10:00:00', null);
    // Outside the requested month — must be excluded.
    $makeSet('2026-05-30 10:00:00', $pageAlpha->id);

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

    // A foreign account + ad set the workspace has no access to.
    $foreign = AdAccount::create(['id' => 999, 'name' => 'Foreign']);
    AdSet::create([
        'id' => 7777,
        'meta_ads_account_id' => $foreign->id,
        'meta_ads_campaign_id' => 5000,
        'meta_page_id' => 9101,
        'name' => 'Foreign Ad Set',
        'created_time' => '2026-06-10 09:00:00',
    ]);

    $this->get(adsCalendarUrl($workspace, ['month' => '2026-06']))
        ->assertOk()
        // June 10 stays at 3 — the foreign account's ad set is not counted.
        ->assertInertia(fn (Assert $page) => $page->where('days.2026-06-10.total', 3));
});

it('forbids non-members', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(adsCalendarUrl($workspace))
        ->assertForbidden();
});
