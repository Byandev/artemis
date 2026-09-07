<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\PageDailyRecord;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;

/**
 * The per-page half of the nightly performance build. It is what the Sales &
 * Marketing dashboard's product comparison reads, by way of the shop that links
 * a page to its product, so a workspace it skips has no product comparison.
 */
function buildPagePerfPage(Workspace $workspace): Page
{
    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($workspace)->create()->id,
    ]);
}

/** A confirmed order on a page, at the amount the day should sum to. */
function buildPagePerfOrder(Workspace $workspace, Page $page, string $date, float $amount): void
{
    Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
        'page_id' => $page->id,
        // Any status but the two the build excludes (6 = cancelled, 7 = returned).
        'status' => 1,
        'status_name' => 'confirmed',
        'order_number' => (string) fake()->unique()->numberBetween(100000, 999999),
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => $date.' 09:00:00',
        'total_amount' => $amount,
        'confirmed_at' => $date.' 10:00:00',
    ]);
}

test('it builds a row for a Gencys partner\'s Pancake pages', function () {
    // `source` names the pipeline that produced the row, not the pipeline the
    // workspace's advertiser figures come from — and a Pancake page is Artemis
    // data wherever it lives. Skipping these workspaces left them with no
    // per-page history at all, despite having the pages and orders to build it.
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 1500);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])
        ->assertSuccessful();

    $row = PageDailyRecord::where('workspace_id', $workspace->id)
        ->where('page_id', $page->id)
        ->where('date', '2026-03-10')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe(PageDailyRecord::SOURCE_ARTEMIS)
        ->and($row->page_type)->toBe((new Page)->getMorphClass())
        ->and((float) $row->sales)->toBe(1500.0)
        ->and((int) $row->orders)->toBe(1);
});

test('it still builds rows for an ordinary workspace', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 900);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])
        ->assertSuccessful();

    expect((float) PageDailyRecord::where('page_id', $page->id)->value('sales'))->toBe(900.0);
});

test('re-running a date updates the row rather than stacking duplicates', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 500);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10']);
    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10']);

    expect(PageDailyRecord::where('page_id', $page->id)->count())->toBe(1)
        ->and((float) PageDailyRecord::where('page_id', $page->id)->value('sales'))->toBe(500.0);
});

test('it snapshots the budget the page was on that day', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);

    // A budget set once, then raised a week later. A budget carries forward
    // until it is changed, so the day in between is measured against the first.
    PageDailyBudgetRecord::create([
        'workspace_id' => $workspace->id,
        'page_id' => $page->id,
        'date' => '2026-03-01',
        'budget' => 1000,
    ]);
    PageDailyBudgetRecord::create([
        'workspace_id' => $workspace->id,
        'page_id' => $page->id,
        'date' => '2026-03-08',
        'budget' => 2500,
    ]);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-05'])->assertSuccessful();
    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $budgetOn = fn (string $date) => PageDailyRecord::where('page_id', $page->id)
        ->where('date', $date)
        ->value('ad_spend_budget');

    expect((float) $budgetOn('2026-03-05'))->toBe(1000.0)
        ->and((float) $budgetOn('2026-03-10'))->toBe(2500.0);
});

test('it leaves the budget null for a day before the page had one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 900);

    PageDailyBudgetRecord::create([
        'workspace_id' => $workspace->id,
        'page_id' => $page->id,
        'date' => '2026-03-20',
        'budget' => 1000,
    ]);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    // Nothing was planned yet, so the day has nothing to be under or over —
    // which is not the same as having been planned at zero.
    expect(PageDailyRecord::where('page_id', $page->id)->value('ad_spend_budget'))->toBeNull();
});
