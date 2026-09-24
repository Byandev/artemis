<?php

use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * The public RMO page is reachable by anyone with the link, so once the
 * workspace stops paying it has to close for everyone — page, XHR, exports and
 * writes alike — not just show members the upgrade modal.
 */

beforeEach(function () {
    ['workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->pageUrl = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);
});

test('an active subscription keeps the public page open', function () {
    subscribeWorkspace($this->workspace);

    $this->get($this->pageUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/rts/public-pages/rmo-management'));
});

test('a lapsed subscription shows the expired screen instead of the page', function (string $status) {
    subscribeWorkspace($this->workspace, $status);

    $this->get($this->pageUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('workspaces/public/subscription-expired')
            ->where('workspace.slug', $this->workspace->slug)
            ->missing('orders')
        );
})->with([Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELED, Subscription::STATUS_PAST_DUE]);

test('only the status counts, so an active subscription past its period end stays open', function (string $status) {
    subscribeWorkspace($this->workspace, $status, now()->subMonth());

    $this->get($this->pageUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/rts/public-pages/rmo-management'));
})->with([Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]);

test('a workspace with no subscription at all is shut', function () {
    $this->get($this->pageUrl)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/public/subscription-expired'));
});

test('the endpoints behind the page are refused once lapsed', function () {
    subscribeWorkspace($this->workspace, Subscription::STATUS_EXPIRED);
    $slug = ['workspace' => $this->workspace->slug];

    $this->getJson(route('public-page.rmo-management.stats', $slug))->assertForbidden();
    $this->getJson(route('public-page.rmo-management.callLogs', $slug))->assertForbidden();
    $this->post(route('public-page.rmo-management.verify-password', $slug), ['password' => 'x'])->assertForbidden();
    $this->post(route('public-page.rmo-management.bulkUpdateStatus', $slug), ['ids' => [1], 'status' => 'DELIVERED'])->assertForbidden();
    $this->post(route('public-page.rmo-management.updateStatus', [...$slug, 'id' => 1]), ['status' => 'DELIVERED'])->assertForbidden();
});

test('a signed-in super admin is shut out of a lapsed workspace too', function () {
    subscribeWorkspace($this->workspace, Subscription::STATUS_EXPIRED);

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->get($this->pageUrl)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/public/subscription-expired'));
});

test('the gate holds in the local environment', function () {
    subscribeWorkspace($this->workspace, Subscription::STATUS_EXPIRED);
    app()->detectEnvironment(fn () => 'local');

    $this->get($this->pageUrl)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/public/subscription-expired'));
});
