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
 */
function seedOwnerCalendar($workspace): array
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
    $make('2026-06-12 09:00:00', $benPage->id);
    // No resolvable page — belongs to nobody, so no owner filter should keep it.
    $make('2026-06-14 09:00:00', null);

    return compact('ana', 'ben', 'anaPage', 'benPage');
}

function ownerCalendarUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-calendar', [
        'workspace' => $workspace,
        'month' => '2026-06',
        ...$query,
    ]);
}

it('lists the owners of visible pages as filter options', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pageOwnerOptions.0.name', 'Ana Owner')
            ->where('pageOwnerOptions.0.id', (string) $ctx['ana']->id)
            ->where('pageOwnerOptions.1.name', 'Ben Owner')
        );
});

it('shows every campaign when no owner is selected', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-10.total', 2)
            ->where('days.2026-06-12.total', 1)
            ->where('days.2026-06-14.total', 1)
        );
});

it('narrows the calendar to a single page owner', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace, ['page_owners' => [$ctx['ana']->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPageOwners', [(string) $ctx['ana']->id])
            ->where('days.2026-06-10.total', 2)
            ->missing('days.2026-06-12')
            ->missing('days.2026-06-14')
        );
});

it('accepts several owners at once', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace, [
        'page_owners' => [$ctx['ana']->id, $ctx['ben']->id],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2026-06-10.total', 2)
            ->where('days.2026-06-12.total', 1)
            // Still excluded: an unassigned campaign has no owner.
            ->missing('days.2026-06-14')
        );
});

it('drops unassigned campaigns once an owner is selected', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace, [
        'page_owners' => [$ctx['ben']->id],
        'pages' => ['none'],
    ]))
        ->assertOk()
        // 'none' would normally surface the page-less campaign; the owner
        // condition is an inner one, so it can't survive.
        ->assertInertia(fn (Assert $page) => $page->missing('days.2026-06-14'));
});

it('combines the page filter with the owner filter', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedOwnerCalendar($workspace);

    // Ben's page, but filtered to Ana as owner — no overlap, so nothing shows.
    $this->get(ownerCalendarUrl($workspace, [
        'pages' => [(string) $ctx['benPage']->id],
        'page_owners' => [$ctx['ana']->id],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('days', []));
});

it('ignores a blank or non-numeric owner id', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedOwnerCalendar($workspace);

    $this->get(ownerCalendarUrl($workspace, ['page_owners' => ['', 'abc']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPageOwners', [])
            ->where('days.2026-06-14.total', 1)
        );
});
