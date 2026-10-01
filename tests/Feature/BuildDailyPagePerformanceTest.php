<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\PageDailyRecord;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order as PancakeOrder;
use Modules\Pancake\Models\OrderItem;

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
    PancakeOrder::create([
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

test('it opens the day up to units sold and what the goods cost', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 900);

    $order = PancakeOrder::where('page_id', $page->id)->firstOrFail();

    $line = fn (int $quantity, ?float $cogs) => OrderItem::create([
        'order_id' => $order->id,
        'pancake_id' => (string) Str::uuid(),
        'pancake_order_id' => $order->order_number,
        'quantity' => $quantity,
        'cogs' => $cogs,
    ]);

    $line(2, 300);
    // An un-costed line still sold units, and adds nothing to the cost.
    $line(3, null);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $row = PageDailyRecord::where('page_id', $page->id)->where('date', '2026-03-10')->first();

    expect((int) $row->item_quantity)->toBe(5)
        ->and((float) $row->order_cogs)->toBe(300.0);
});

test('it leaves the cost null on a day whose lines carry none', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 900);

    $order = PancakeOrder::where('page_id', $page->id)->firstOrFail();

    OrderItem::create([
        'order_id' => $order->id,
        'pancake_id' => (string) Str::uuid(),
        'pancake_order_id' => $order->order_number,
        'quantity' => 4,
        'cogs' => null,
    ]);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $row = PageDailyRecord::where('page_id', $page->id)->where('date', '2026-03-10')->first();

    // Units are a count and read as 4; the cost is "nothing recorded", not zero.
    expect((int) $row->item_quantity)->toBe(4)
        ->and($row->order_cogs)->toBeNull();
});

test('it rates a day by the RTS of the 30 days ending on it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);

    $history = fn (string $date, float $returning, float $delivered) => PageDailyRecord::create([
        'workspace_id' => $workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => $page->getMorphClass(),
        'page_id' => $page->id,
        'date' => $date,
        'returning_amount' => $returning,
        'delivered_amount' => $delivered,
    ]);

    // Inside the window: ₱300 back against ₱700 out.
    $history('2026-03-05', 300, 700);
    // 30 days before the build date — one day too old to count, and a rate so
    // extreme that letting it in would be obvious.
    $history('2026-02-08', 9000, 0);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $row = PageDailyRecord::where('page_id', $page->id)->where('date', '2026-03-10')->first();

    expect((float) $row->rts_rate_30d)->toBe(30.0);
});

test('it leaves the trailing rate null when nothing moved in the window', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);
    buildPagePerfOrder($workspace, $page, '2026-03-10', 900);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $row = PageDailyRecord::where('page_id', $page->id)->where('date', '2026-03-10')->first();

    // No deliveries and no returns is no rate — the tracker falls back to its
    // default rather than pricing the page as one that never gets returns.
    expect($row->rts_rate_30d)->toBeNull();
});

test('it counts the day it is building in that day own window', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $page = buildPagePerfPage($workspace);

    // A parcel that came back on the build date itself, and one that landed.
    // The row does not exist yet, so these have to be folded in as they are
    // computed rather than read back from a row written by an earlier run.
    buildPagePerfOrder($workspace, $page, '2026-03-10', 400);
    PancakeOrder::where('page_id', $page->id)->update([
        'returning_at' => '2026-03-10 09:00:00',
        // The shared helper fills total_amount; the build reads final_amount.
        'final_amount' => 400,
    ]);

    $this->artisan('build-page-daily-performance', ['--date' => '2026-03-10'])->assertSuccessful();

    $row = PageDailyRecord::where('page_id', $page->id)->where('date', '2026-03-10')->first();

    // Everything that moved that day went back.
    expect((float) $row->rts_rate_30d)->toBe(100.0);
});
