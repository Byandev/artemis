<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use Modules\MetaAds\Jobs\CaptureBudgetSnapshots;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;

/**
 * Seed an ad set tagged with the given page so the per-page rollup has a budget
 * to sum. Ad sets carry meta_page_id directly, and a Pancake page's id IS the FB
 * page id, so $pageId doubles as both.
 */
function seedPageAdSet(int $pageId, ?float $daily, ?float $lifetime = null): void
{
    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create(['id' => $pageId * 10 + 1, 'meta_ads_account_id' => $account->id, 'name' => 'C', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
    AdSet::create([
        'id' => $pageId * 10 + 2,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'meta_page_id' => $pageId,
        'name' => 'AS',
        'daily_budget' => $daily,
        'lifetime_budget' => $lifetime,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);
}

it('writes the page daily budget record from the summed daily budget', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 729182576936579]);

    seedPageAdSet($page->id, daily: 500.00);

    (new CaptureBudgetSnapshots('2026-06-13'))->handle();

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect($record)->not->toBeNull()
        ->and($record->workspace_id)->toBe($workspace->id)
        ->and((float) $record->budget)->toBe(500.00);
});

it('writes a zero page daily budget record for lifetime-only budgets', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 745492068656489]);

    seedPageAdSet($page->id, daily: null, lifetime: 9000.00);

    (new CaptureBudgetSnapshots('2026-06-13'))->handle();

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->budget)->toBe(0.00);
});

it('skips pages that do not exist locally', function () {
    actingAsWorkspaceOwner();
    // meta_page_id with no matching local Page row.
    seedPageAdSet(999000111, daily: 300.00);

    (new CaptureBudgetSnapshots('2026-06-13'))->handle();

    expect(PageDailyBudgetRecord::count())->toBe(0);
});
