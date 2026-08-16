<?php

use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Two owners with a page each, plus a campaign whose ad set resolves to no page
 * at all. The calendar groups by `campaigns.start_time`, so that is the date
 * seeded here (not created_time).
 *
 * Ana owns TWO pages, which is the case that matters: the page filter's owner
 * label is only a label, so both of her pages stay separate options and the
 * calendar keeps counting them separately.
 */
function seedLabelCalendar($workspace): array
{
    $metaUser = MetaUser::create(['id' => 8201, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 301, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $ana = User::factory()->create(['name' => 'Ana Owner']);
    $ben = User::factory()->create(['name' => 'Ben Owner']);
    $workspace->users()->attach([$ana->id, $ben->id]);

    // A Pancake page's primary key IS the FB page id (== meta_page_id).
    $anaPage = Page::factory()->forWorkspace($workspace)->create([
        'id' => 9201, 'name' => 'Ana Page', 'owner_id' => $ana->id,
    ]);
    $anaSecondPage = Page::factory()->forWorkspace($workspace)->create([
        'id' => 9203, 'name' => 'Ana Second Page', 'owner_id' => $ana->id,
    ]);
    $benPage = Page::factory()->forWorkspace($workspace)->create([
        'id' => 9202, 'name' => 'Ben Page', 'owner_id' => $ben->id,
    ]);

    $cid = 7000;
    $sid = 8000;
    $make = function (string $startTime, ?int $pageId) use (&$cid, &$sid, $account) {
        $campaign = Campaign::create([
            'id' => $cid++,
            'meta_ads_account_id' => $account->id,
            'name' => 'Campaign '.$cid,
            'start_time' => $startTime,
        ]);

        AdSet::create([
            'id' => $sid++,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$sid,
        ]);

        return $campaign;
    };

    $make('2026-06-10 09:00:00', $anaPage->id);
    $make('2026-06-10 11:00:00', $anaPage->id);
    $make('2026-06-11 09:00:00', $anaSecondPage->id);
    $make('2026-06-12 09:00:00', $benPage->id);
    // No resolvable page — surfaces under the "Unassigned" bucket.
    $make('2026-06-14 09:00:00', null);

    return compact('ana', 'ben', 'anaPage', 'anaSecondPage', 'benPage');
}

function labelCalendarUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-calendar', [
        'workspace' => $workspace,
        'month' => '2026-06',
        ...$query,
    ]);
}

it('carries both labels on every page option', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    $this->get(labelCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pageOptions.0.name', 'Ana Page')
            ->where('pageOptions.0.owner_name', 'Ana Owner')
            ->where('pageOptions.1.name', 'Ana Second Page')
            ->where('pageOptions.1.owner_name', 'Ana Owner')
            ->where('pageOptions.2.name', 'Ben Page')
            ->where('pageOptions.2.owner_name', 'Ben Owner')
        );
});

it('defaults the page label to the page name', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    $this->get(labelCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pageLabel', 'name'));
});

it('echoes the owner label back when asked for', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    $this->get(labelCalendarUrl($workspace, ['page_label' => 'owner']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pageLabel', 'owner'));
});

it('falls back to the page name for an unrecognised label', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    $this->get(labelCalendarUrl($workspace, ['page_label' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pageLabel', 'name'));
});

it('counts campaigns per page regardless of the label shown', function (string $label) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    // The whole point of the toggle being display-only: Ana's two pages stay
    // two buckets, and every day keeps the same totals.
    $this->get(labelCalendarUrl($workspace, ['page_label' => $label]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-10.total', 2)
            ->where('days.2026-06-11.total', 1)
            ->where('days.2026-06-12.total', 1)
            ->where('days.2026-06-14.total', 1)
            ->where('pageTotals', fn ($totals) => collect($totals)->count() === 4)
        );
})->with(['name', 'owner']);

it('keeps showing page names in the calendar under the owner label', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    $this->get(labelCalendarUrl($workspace, ['page_label' => 'owner']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-10.pages.0.name', 'Ana Page')
            ->where('days.2026-06-11.pages.0.name', 'Ana Second Page')
        );
});

it('still filters by page while the owner label is shown', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedLabelCalendar($workspace);

    // Selecting one of Ana's pages must not drag in her other one — the owner
    // label groups nothing.
    $this->get(labelCalendarUrl($workspace, [
        'page_label' => 'owner',
        'pages' => [(string) $ctx['anaPage']->id],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-10.total', 2)
            ->missing('days.2026-06-11')
            ->missing('days.2026-06-12')
            ->missing('days.2026-06-14')
        );
});

it('labels a blank-named owner and the unassigned bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLabelCalendar($workspace);

    // pages.owner_id is NOT NULL, so a page always has an owner — but that
    // member's name can be blank, which would otherwise render an empty option.
    $nameless = User::factory()->create(['name' => '']);
    $workspace->users()->attach($nameless->id);
    Page::factory()->forWorkspace($workspace)->create([
        'id' => 9204, 'name' => 'Orphan Page', 'owner_id' => $nameless->id,
    ]);

    // Options are ordered by page name, so: Ana Page, Ana Second Page, Ben
    // Page, Orphan Page — then the appended "Unassigned" bucket.
    $this->get(labelCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pageOptions.3.name', 'Orphan Page')
            ->where('pageOptions.3.owner_name', 'Unnamed member')
            // The month has a page-less campaign, so the bucket is offered —
            // and stays identifiable under either label.
            ->where('pageOptions.4.id', 'none')
            ->where('pageOptions.4.name', 'Unassigned page')
            ->where('pageOptions.4.owner_name', 'Unassigned page')
        );
});

it('no longer accepts the removed page owner filter', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedLabelCalendar($workspace);

    // The old filter narrowed the calendar; now it is simply ignored.
    $this->get(labelCalendarUrl($workspace, ['page_owners' => [$ctx['ana']->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('pageOwnerOptions')
            ->missing('selectedPageOwners')
            ->where('days.2026-06-12.total', 1)
            ->where('days.2026-06-14.total', 1)
        );
});
