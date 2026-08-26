<?php

use App\Support\SalesMarketingDashboard;
use Inertia\Testing\AssertableInertia;

/**
 * Daily Report used to be the default tab of the tabbed S&M dashboard. It is now
 * a standalone page owned by the SalesMarketing module, sharing the one
 * `sales_marketing_dashboard_module_enabled` toggle with the dashboard tabs.
 */

/** A workspace with Sales & Marketing on, plus its owner. */
function dailyReportContext(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return ['owner' => $owner, 'workspace' => $workspace];
}

test('the daily report renders on its own route', function () {
    ['owner' => $owner, 'workspace' => $workspace] = dailyReportContext();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-report")
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('workspaces/sales-marketing/daily-report/index')
                ->where('baseUrl', "/workspaces/{$workspace->slug}/sales-marketing/daily-report")
                // Standalone now — the page is no longer handed a tab bar.
                ->missing('tabs')
        );
});

test('the daily report data endpoint returns the view payload', function () {
    ['owner' => $owner, 'workspace' => $workspace] = dailyReportContext();

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/sales-marketing/daily-report/data")
        ->assertOk()
        ->assertJsonStructure(['view', 'filters' => ['date']]);
});

test('the daily report 404s when sales & marketing is off', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-report")
        ->assertNotFound();
});

test('the daily report and the dashboard tabs share one toggle', function () {
    ['owner' => $owner, 'workspace' => $workspace] = dailyReportContext();

    // One switch turns the whole feature area off — page and tabs together.
    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-report")
        ->assertNotFound();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/dashboard/page-roas-tracker")
        ->assertNotFound();
});

test('the old dashboard path redirects to the daily report', function () {
    ['owner' => $owner, 'workspace' => $workspace] = dailyReportContext();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/dashboard")
        ->assertRedirect("/workspaces/{$workspace->slug}/sales-marketing/daily-report");
});

test('the dashboard tab bar no longer lists daily report', function () {
    ['workspace' => $workspace] = dailyReportContext();

    $tabs = SalesMarketingDashboard::tabs($workspace);

    expect(collect($tabs)->pluck('key')->all())->not->toContain('daily-report')
        ->and($tabs[0]['key'])->toBe('page-roas-tracker');
});
