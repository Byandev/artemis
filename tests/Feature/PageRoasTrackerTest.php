<?php

use App\Models\Page;
use App\Models\PageDailyRecord;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The tracker's arithmetic, not its markup: amounts sum (and divide on the
 * Average row), ratios stay blended over the range, and `returning_amount` is
 * carried as a closing snapshot rather than summed.
 */
beforeEach(function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    $this->workspace = $workspace;
    $this->page = Page::factory()->create(['workspace_id' => $workspace->id]);
});

/** One day's row, with the ingredients the tracker reads. */
function record(array $attrs): void
{
    PageDailyRecord::create(array_merge([
        'workspace_id' => test()->workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => test()->page->getMorphClass(),
        'page_id' => test()->page->getKey(),
    ], $attrs));
}

function trackerPage(string $start, string $end): array
{
    $response = test()->get(route(
        'workspaces.sales-marketing.dashboard.page-roas-tracker',
        [test()->workspace, 'start' => $start, 'end' => $end],
    ));

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    return $props['pages'][0];
}

it('blends every ratio over the range instead of averaging daily ratios', function () {
    // 8 purchases against 250 spend, then 12 against 750.
    record([
        'date' => '2026-08-01',
        'orders' => 10, 'sales' => 1000, 'ad_spent' => 250, 'ad_sales' => 800,
        'ad_cpp' => 8 / 250,
        'returning_amount' => 100, 'returned_amount' => 50, 'delivered_amount' => 500,
    ]);
    record([
        'date' => '2026-08-02',
        'orders' => 30, 'sales' => 3000, 'ad_spent' => 750, 'ad_sales' => 2400,
        'ad_cpp' => 12 / 750,
        'returning_amount' => 200, 'returned_amount' => 150, 'delivered_amount' => 1500,
    ]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    // Amounts sum.
    expect($page['total']['orders'])->toBe(40)
        ->and($page['total']['sales'])->toBe(4000.0)
        ->and($page['total']['ad_spent'])->toBe(1000.0)
        ->and($page['total']['ad_sales'])->toBe(3200.0);

    // Ratios come off the range totals: 4000/1000, 3200/1000, 20 purchases and
    // 40 orders against 1000 spend.
    expect($page['total']['roas'])->toBe(4.0)
        ->and($page['total']['ad_roas'])->toBe(3.2)
        ->and($page['total']['ad_cpp'])->toBe(0.02)
        ->and($page['total']['cpp'])->toBe(0.04);

    // (200 in-flight + 200 returned) / (that + 2000 delivered).
    expect($page['total']['rts_rate'])->toBe(16.67);
});

it('halves the amounts on the Average row but leaves the ratios blended', function () {
    record([
        'date' => '2026-08-01',
        'orders' => 10, 'sales' => 1000, 'ad_spent' => 250, 'ad_sales' => 800,
        'ad_cpp' => 8 / 250,
        'returning_amount' => 100, 'returned_amount' => 50, 'delivered_amount' => 500,
    ]);
    record([
        'date' => '2026-08-02',
        'orders' => 30, 'sales' => 3000, 'ad_spent' => 750, 'ad_sales' => 2400,
        'ad_cpp' => 12 / 750,
        'returning_amount' => 200, 'returned_amount' => 150, 'delivered_amount' => 1500,
    ]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    expect($page['average']['orders'])->toBe(20)
        ->and($page['average']['sales'])->toBe(2000.0)
        ->and($page['average']['ad_spent'])->toBe(500.0);

    // A mean of daily ROAS would be 4.00 here too, so lean on ad_cpp: the daily
    // ratios are 0.032 and 0.016 (mean 0.024), while the blend is 20/1000.
    expect($page['average']['ad_cpp'])->toBe(0.02)
        ->and($page['average']['roas'])->toBe(4.0)
        ->and($page['average']['rts_rate'])->toBe(16.67);
});

it('carries returning as a closing snapshot rather than summing the days', function () {
    record(['date' => '2026-08-01', 'returning_amount' => 100]);
    record(['date' => '2026-08-02', 'returning_amount' => 250]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    // Not 350 — the second day already counts everything still on the way back.
    expect($page['total']['returning_amount'])->toBe(250.0)
        // A stock is not divided by the day count either.
        ->and($page['average']['returning_amount'])->toBe(250.0);
});

it('carries the last known snapshot through a day the builder skipped', function () {
    record(['date' => '2026-08-01', 'returning_amount' => 100]);
    // Nothing at all for 08-02 and 08-03.

    $page = trackerPage('2026-08-01', '2026-08-03');

    // The day cells read zero — there is no figure to show — but the range keeps
    // the last one the builder actually wrote.
    expect($page['days']['2026-08-03']['returning_amount'])->toBe(0.0)
        ->and($page['total']['returning_amount'])->toBe(100.0);
});

it('lets a written zero clear the snapshot', function () {
    record(['date' => '2026-08-01', 'returning_amount' => 100]);
    // A row exists but carries no returning figure — everything came back in.
    record(['date' => '2026-08-02', 'orders' => 5, 'sales' => 500]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    expect($page['total']['returning_amount'])->toBe(0.0);
});

it('sends every metric so the column toggle needs no round trip', function () {
    record(['date' => '2026-08-01', 'orders' => 1, 'sales' => 10, 'ad_spent' => 5]);

    $response = $this->get(route(
        'workspaces.sales-marketing.dashboard.page-roas-tracker',
        [$this->workspace, 'start' => '2026-08-01', 'end' => '2026-08-01'],
    ));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('workspaces/page-roas-tracker/index')
        ->has('pages.0.days.2026-08-01', fn (Assert $day) => $day
            ->hasAll([
                'orders', 'sales', 'ad_spent', 'ad_sales', 'returning_amount',
                'roas', 'ad_roas', 'rts_rate', 'ad_cpp', 'cpp',
            ])
        )
    );
});
