<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use Modules\MetaAds\Jobs\CaptureBudgetSnapshots;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Creative;

/**
 * Wire one ad → creative (pointing at $pageId) → ad set (with the given budgets)
 * so the per-page rollup has something to sum. A Pancake page's id IS the FB
 * page id, so $pageId doubles as both.
 */
function seedPageAdSet(int $pageId, ?float $daily, ?float $lifetime = null): void
{
    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create(['id' => $pageId * 10 + 1, 'meta_ads_account_id' => $account->id, 'name' => 'C', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
    $adSet = AdSet::create([
        'id' => $pageId * 10 + 2,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'AS',
        'daily_budget' => $daily,
        'lifetime_budget' => $lifetime,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);
    $creative = Creative::create(['id' => $pageId * 10 + 3, 'meta_ads_account_id' => $account->id, 'meta_page_id' => $pageId]);
    Ad::create([
        'id' => $pageId * 10 + 4,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'meta_ads_set_id' => $adSet->id,
        'meta_ads_creative_id' => $creative->id,
        'name' => 'Ad',
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
