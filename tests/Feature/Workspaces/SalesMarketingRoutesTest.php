<?php

use App\Models\Team;

/**
 * Sales & Marketing used to be one tabbed dashboard at
 * `/sales-marketing/dashboard/{tab}`. It is now a group of sibling pages, each
 * with its own URL and its own entry in the sidebar group — including a
 * Dashboard page that has taken the bare `/dashboard` URL back.
 *
 * These cover the seam: the flat URLs resolve, and the tabbed ones people have
 * bookmarked (and the three legacy entry points elsewhere in the route file)
 * still arrive at the right page rather than a 404.
 */
function smWorkspace(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update([
        'sales_marketing_dashboard_module_enabled' => true,
        'ad_spend_goals_module_enabled' => true,
    ]);

    Team::factory()->create(['workspace_id' => $workspace->id]);

    return ['owner' => $owner, 'workspace' => $workspace];
}

test('each page answers on its own flat URL', function (string $path) {
    ['owner' => $owner, 'workspace' => $workspace] = smWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/{$path}")
        ->assertOk();
})->with([
    'dashboard',
    'daily-report',
    'page-roas-tracker',
    'ad-spend-goals',
    'sales-targets',
]);

test('the old tab URLs redirect to the page that replaced them', function (string $tab, string $expected) {
    ['owner' => $owner, 'workspace' => $workspace] = smWorkspace();

    $base = "/workspaces/{$workspace->slug}/sales-marketing";

    $this->actingAs($owner)
        ->get("{$base}/dashboard/{$tab}")
        ->assertRedirect("{$base}/{$expected}");
})->with([
    ['page-roas-tracker', 'page-roas-tracker'],
    ['ad-spend-goals', 'ad-spend-goals'],
    ['ad-spent-summary', 'ad-spent-summary'],
    ['sales-targets', 'sales-targets'],
    // An unknown segment is better off at the first page than at a 404. The
    // bare `/dashboard` is no longer a redirect — it is the group's own page.
    ['who-knows', 'daily-report'],
]);

test('the legacy entry points elsewhere in the app still land', function (string $from, string $expected) {
    ['owner' => $owner, 'workspace' => $workspace] = smWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/{$from}")
        ->assertRedirect("/workspaces/{$workspace->slug}/sales-marketing/{$expected}");
})->with([
    ['page-roas-tracker', 'page-roas-tracker'],
    ['ad-spend-goals', 'ad-spend-goals'],
    ['integrations/meta/ad-spent-summary', 'ad-spent-summary'],
]);

test('the pages no longer ship a tab bar', function () {
    ['owner' => $owner, 'workspace' => $workspace] = smWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-report")
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/daily-report/index')
            ->missing('tabs')
            ->missing('activeTab')
        );
});

test('the module switch still gates the whole group', function (string $path) {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/{$path}")
        ->assertNotFound();
})->with(['daily-report', 'page-roas-tracker', 'sales-targets']);
