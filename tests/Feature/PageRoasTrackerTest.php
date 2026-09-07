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
        'workspaces.sales-marketing.page-roas-tracker',
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
        'ad_purchases' => 8,
        'returning_amount' => 100, 'delivered_amount' => 500, 'rts_rate' => 20,
    ]);
    record([
        'date' => '2026-08-02',
        'orders' => 30, 'sales' => 3000, 'ad_spent' => 750, 'ad_sales' => 2400,
        'ad_purchases' => 12,
        'returning_amount' => 200, 'delivered_amount' => 1500, 'rts_rate' => 30,
    ]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    // Amounts sum.
    expect($page['total']['orders'])->toBe(40)
        ->and($page['total']['sales'])->toBe(4000.0)
        ->and($page['total']['ad_spent'])->toBe(1000.0)
        ->and($page['total']['ad_sales'])->toBe(3200.0)
        // Delivered is a flow, so unlike returning it does sum.
        ->and($page['total']['delivered_amount'])->toBe(2000.0)
        // 8 + 12 Meta purchases — the denominator behind ad_cpp, now its own column.
        ->and($page['total']['ad_purchases'])->toBe(20)
        ->and($page['average']['ad_purchases'])->toBe(10);

    // Ratios come off the range totals: 4000/1000, 3200/1000, and 1000 spend
    // against 20 Meta purchases / 40 Pancake orders.
    expect($page['total']['roas'])->toBe(4.0)
        ->and($page['total']['ad_roas'])->toBe(3.2)
        ->and($page['total']['ad_cpp'])->toBe(50.0)
        ->and($page['total']['cpp'])->toBe(25.0);

    // 300 returning over 300 returning + 2000 delivered — the whole range, not
    // the mean of the two daily rates (which would be 25.0).
    expect($page['total']['rts_rate'])->toBe(13.04);
});

it('halves the amounts on the Average row but leaves the ratios blended', function () {
    record([
        'date' => '2026-08-01',
        'orders' => 10, 'sales' => 1000, 'ad_spent' => 250, 'ad_sales' => 800,
        'ad_purchases' => 8,
        'returning_amount' => 100, 'delivered_amount' => 500, 'rts_rate' => 20,
    ]);
    record([
        'date' => '2026-08-02',
        'orders' => 30, 'sales' => 3000, 'ad_spent' => 750, 'ad_sales' => 2400,
        'ad_purchases' => 12,
        'returning_amount' => 200, 'delivered_amount' => 1500, 'rts_rate' => 30,
    ]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    expect($page['average']['orders'])->toBe(20)
        ->and($page['average']['sales'])->toBe(2000.0)
        ->and($page['average']['ad_spent'])->toBe(500.0)
        ->and($page['average']['delivered_amount'])->toBe(1000.0);

    // A mean of daily ROAS would be 4.00 here too, so lean on ad_cpp: the daily
    // costs are 250/8 and 750/12 (mean 46.88), while the blend is 1000/20.
    expect($page['average']['ad_cpp'])->toBe(50.0)
        ->and($page['average']['roas'])->toBe(4.0)
        ->and($page['average']['rts_rate'])->toBe(13.04);
});

it('sums returning like any other daily amount', function () {
    record(['date' => '2026-08-01', 'returning_amount' => 100]);
    record(['date' => '2026-08-02', 'returning_amount' => 250]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    // The builder writes what started its way back on that date, so the days do
    // not overlap and the range is their sum.
    expect($page['total']['returning_amount'])->toBe(350.0)
        ->and($page['average']['returning_amount'])->toBe(175.0);
});

it('treats a day the builder skipped as nothing returning', function () {
    record(['date' => '2026-08-01', 'returning_amount' => 100]);
    // Nothing at all for 08-02 and 08-03.

    $page = trackerPage('2026-08-01', '2026-08-03');

    expect($page['days']['2026-08-03']['returning_amount'])->toBe(0.0)
        ->and($page['total']['returning_amount'])->toBe(100.0);
});

it('shows a day exactly as the builder stored it, deriving nothing', function () {
    // Ratios that disagree with their own ingredients on purpose: if the tracker
    // recomputed any of them, it would overwrite these with 2.00 / 5.00 / 2.50.
    record([
        'date' => '2026-08-01',
        'orders' => 20, 'sales' => 1000, 'ad_spent' => 500,
        'ad_sales' => 2500, 'ad_purchases' => 100,
        'roas' => 9.11, 'ad_roas' => 9.22, 'rts_rate' => 9.33,
        'ad_cpp' => 9.44, 'cpp' => 9.55,
    ]);

    $day = trackerPage('2026-08-01', '2026-08-01')['days']['2026-08-01'];

    expect($day['roas'])->toBe(9.11)
        ->and($day['ad_roas'])->toBe(9.22)
        ->and($day['rts_rate'])->toBe(9.33)
        ->and($day['ad_cpp'])->toBe(9.44)
        ->and($day['cpp'])->toBe(9.55);
});

it('leaves a ratio the builder never wrote as null rather than zero', function () {
    // No spend, so the builder has no denominator — "no ROAS" is not "ROAS 0".
    record(['date' => '2026-08-01', 'orders' => 3, 'sales' => 300]);

    $day = trackerPage('2026-08-01', '2026-08-01')['days']['2026-08-01'];

    expect($day['roas'])->toBeNull()
        ->and($day['cpp'])->toBeNull()
        ->and($day['sales'])->toBe(300.0);
});

it('weights RTS by what actually moved, not by the day', function () {
    // A quiet day at 50% and a heavy one at 10%. A mean of the daily rates would
    // call that 30%; the blend says 12%, because the heavy day is most of the
    // parcels.
    record(['date' => '2026-08-01', 'returning_amount' => 100, 'delivered_amount' => 100]);
    record(['date' => '2026-08-02', 'returning_amount' => 200, 'delivered_amount' => 1800]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    expect($page['total']['rts_rate'])->toBe(13.64);
});

it('leaves RTS null on a range with no delivery activity at all', function () {
    // Nothing moved, so there is no rate — not a 0% one.
    record(['date' => '2026-08-01', 'orders' => 5, 'sales' => 500]);

    $page = trackerPage('2026-08-01', '2026-08-01');

    expect($page['total']['rts_rate'])->toBeNull();
});

it('rolls every visible page into one all-pages group', function () {
    $second = Page::factory()->create(['workspace_id' => $this->workspace->id]);

    $row = fn (array $attrs) => PageDailyRecord::create(array_merge([
        'workspace_id' => $this->workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => $second->getMorphClass(),
        'page_id' => $second->getKey(),
    ], $attrs));

    record(['date' => '2026-08-01', 'orders' => 10, 'sales' => 1000, 'ad_spent' => 500,
        'returning_amount' => 400, 'delivered_amount' => 1600]);
    $row(['date' => '2026-08-01', 'orders' => 30, 'sales' => 5000, 'ad_spent' => 500,
        'returning_amount' => 500, 'delivered_amount' => 500]);

    $response = test()->get(route(
        'workspaces.sales-marketing.page-roas-tracker',
        [$this->workspace, 'start' => '2026-08-01', 'end' => '2026-08-01'],
    ));

    $overall = $response->viewData('page')['props']['overall'];

    // Amounts add up across the pages...
    expect($overall['days']['2026-08-01']['orders'])->toBe(40)
        ->and($overall['days']['2026-08-01']['sales'])->toBe(6000.0)
        ->and($overall['days']['2026-08-01']['ad_spent'])->toBe(1000.0);

    // ...while ROAS is the combined sales over the combined spend, not 2.00 +
    // 10.00 and not their 6.00 mean.
    expect($overall['days']['2026-08-01']['roas'])->toBe(6.0)
        ->and($overall['total']['roas'])->toBe(6.0);

    // RTS blends across the pages too: 900 back against 900 + 2100 moved.
    expect($overall['days']['2026-08-01']['rts_rate'])->toBe(30.0);

    // Returning adds up across pages exactly as it does across days.
    expect($overall['total']['returning_amount'])->toBe(900.0);
});

it('sends no all-pages group when nothing is in view', function () {
    $response = test()->get(route(
        'workspaces.sales-marketing.page-roas-tracker',
        [$this->workspace, 'start' => '2026-08-01', 'end' => '2026-08-01'],
    ));

    expect($response->viewData('page')['props']['overall'])->toBeNull();
});

it('reads the budget gap off the day it was measured against', function () {
    // Under on the first day, over on the second.
    record(['date' => '2026-08-01', 'ad_spent' => 800, 'ad_spend_budget' => 1000]);
    record(['date' => '2026-08-02', 'ad_spent' => 1400, 'ad_spend_budget' => 1000]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    expect($page['days']['2026-08-01']['ad_spend_budget'])->toBe(1000.0)
        ->and($page['days']['2026-08-01']['budget_variance'])->toBe(-200.0)
        ->and($page['days']['2026-08-01']['budget_pace'])->toBe(80.0);

    expect($page['days']['2026-08-02']['budget_variance'])->toBe(400.0)
        ->and($page['days']['2026-08-02']['budget_pace'])->toBe(140.0);
});

it('sums the budget over the range and compares it against the summed spend', function () {
    record(['date' => '2026-08-01', 'ad_spent' => 800, 'ad_spend_budget' => 1000]);
    record(['date' => '2026-08-02', 'ad_spent' => 1400, 'ad_spend_budget' => 1000]);

    $page = trackerPage('2026-08-01', '2026-08-02');

    // ₱2,200 spent against ₱2,000 planned — ₱200 over, at 110% of plan. The two
    // days pull opposite ways, so a mean of the daily paces (110%) only agrees
    // here by coincidence; the ₱200 is what the range actually says.
    expect($page['total']['ad_spend_budget'])->toBe(2000.0)
        ->and($page['total']['budget_variance'])->toBe(200.0)
        ->and($page['total']['budget_pace'])->toBe(110.0);

    // The variance is an amount, so the Average row divides it; the pace is a
    // ratio, so it stays the range's.
    expect($page['average']['ad_spend_budget'])->toBe(1000.0)
        ->and($page['average']['budget_variance'])->toBe(100.0)
        ->and($page['average']['budget_pace'])->toBe(110.0);
});

it('leaves a page with no budget on record blank rather than calling it overspent', function () {
    // Spend, but nobody ever budgeted the page.
    record(['date' => '2026-08-01', 'ad_spent' => 500]);

    $page = trackerPage('2026-08-01', '2026-08-01');

    expect($page['days']['2026-08-01']['ad_spend_budget'])->toBeNull()
        ->and($page['days']['2026-08-01']['budget_variance'])->toBeNull()
        ->and($page['days']['2026-08-01']['budget_pace'])->toBeNull()
        ->and($page['total']['budget_variance'])->toBeNull()
        ->and($page['total']['budget_pace'])->toBeNull();
});

it('blends the budget across pages in the all-pages group', function () {
    $second = Page::factory()->create(['workspace_id' => $this->workspace->id]);

    record(['date' => '2026-08-01', 'ad_spent' => 800, 'ad_spend_budget' => 1000]);

    PageDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => $second->getMorphClass(),
        'page_id' => $second->getKey(),
        'date' => '2026-08-01',
        'ad_spent' => 1400,
        'ad_spend_budget' => 1000,
    ]);

    $response = test()->get(route(
        'workspaces.sales-marketing.page-roas-tracker',
        [$this->workspace, 'start' => '2026-08-01', 'end' => '2026-08-01'],
    ));

    $overall = $response->viewData('page')['props']['overall'];

    // One page under, one over — the group is ₱200 over ₱2,000 planned.
    expect($overall['days']['2026-08-01']['ad_spend_budget'])->toBe(2000.0)
        ->and($overall['days']['2026-08-01']['budget_variance'])->toBe(200.0)
        ->and($overall['days']['2026-08-01']['budget_pace'])->toBe(110.0);
});

it('sends every metric so the column toggle needs no round trip', function () {
    record(['date' => '2026-08-01', 'orders' => 1, 'sales' => 10, 'ad_spent' => 5]);

    $response = $this->get(route(
        'workspaces.sales-marketing.page-roas-tracker',
        [$this->workspace, 'start' => '2026-08-01', 'end' => '2026-08-01'],
    ));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('workspaces/sales-marketing/page-roas-tracker/index')
        ->has('pages.0.days.2026-08-01', fn (Assert $day) => $day
            ->hasAll([
                'orders', 'sales', 'ad_spent', 'ad_sales', 'ad_purchases',
                'ad_cpp', 'cpp',
                'ad_spend_budget', 'budget_variance', 'budget_pace',
                'delivered_amount', 'returning_amount',
                'roas', 'ad_roas', 'rts_rate',
            ])
        )
    );
});
