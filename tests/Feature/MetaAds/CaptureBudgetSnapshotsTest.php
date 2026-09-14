<?php

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\BudgetSnapshot;
use Modules\MetaAds\Models\Campaign;

function captureBudgets(string $date): void
{
    test()->artisan('metaads:capture-budgets', ['--date' => $date])->assertSuccessful();
}

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
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 729182576936579, 'auto_update_ad_budget' => true]);

    seedPageAdSet($page->id, daily: 500.00);

    captureBudgets('2026-06-13');

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect($record)->not->toBeNull()
        ->and($record->workspace_id)->toBe($workspace->id)
        ->and((float) $record->budget)->toBe(500.00);
});

it('captures the campaign daily budget when the ad set carries none (CBO)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509182, 'auto_update_ad_budget' => true]);

    // CBO: the budget lives on the campaign and the ad set's own budget is null,
    // but the ad set still tags the page it promotes.
    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create([
        'id' => 900900901,
        'meta_ads_account_id' => $account->id,
        'name' => 'CBO',
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
        'daily_budget' => 750.00,
    ]);
    AdSet::create([
        'id' => 900900902,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'meta_page_id' => $page->id,
        'name' => 'AS',
        'daily_budget' => null,
        'lifetime_budget' => null,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    captureBudgets('2026-06-13');

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->budget)->toBe(750.00);
});

it('sums ad-set and campaign daily budgets on the same page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509200, 'auto_update_ad_budget' => true]);

    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);

    // ABO campaign + ad set carrying its own budget.
    $aboCampaign = Campaign::create(['id' => 900900910, 'meta_ads_account_id' => $account->id, 'name' => 'ABO', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
    AdSet::create([
        'id' => 900900911,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $aboCampaign->id,
        'meta_page_id' => $page->id,
        'name' => 'ABO AS',
        'daily_budget' => 200.00,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    // CBO campaign on the same page, budget on the campaign.
    $cboCampaign = Campaign::create(['id' => 900900912, 'meta_ads_account_id' => $account->id, 'name' => 'CBO', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => 300.00]);
    AdSet::create([
        'id' => 900900913,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $cboCampaign->id,
        'meta_page_id' => $page->id,
        'name' => 'CBO AS',
        'daily_budget' => null,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    captureBudgets('2026-06-13');

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect((float) $record->budget)->toBe(500.00);
});

it('ignores a campaign budget whose ad sets are all paused', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509300, 'auto_update_ad_budget' => true]);

    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create(['id' => 900900920, 'meta_ads_account_id' => $account->id, 'name' => 'CBO', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => 750.00]);
    AdSet::create([
        'id' => 900900921,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'meta_page_id' => $page->id,
        'name' => 'AS',
        'daily_budget' => null,
        'status' => 'PAUSED',
        'effective_status' => 'PAUSED',
    ]);

    captureBudgets('2026-06-13');

    expect(PageDailyBudgetRecord::where('page_id', $page->id)->count())->toBe(0);
});

it('writes a zero page daily budget record for lifetime-only budgets', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 745492068656489, 'auto_update_ad_budget' => true]);

    seedPageAdSet($page->id, daily: null, lifetime: 9000.00);

    captureBudgets('2026-06-13');

    $record = PageDailyBudgetRecord::where('page_id', $page->id)->where('date', '2026-06-13')->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->budget)->toBe(0.00);
});

it('skips pages that do not exist locally', function () {
    actingAsWorkspaceOwner();
    // meta_page_id with no matching local Page row.
    seedPageAdSet(999000111, daily: 300.00);

    captureBudgets('2026-06-13');

    expect(PageDailyBudgetRecord::count())->toBe(0);
});

it('snapshots each ad set budget for the date', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509301, 'auto_update_ad_budget' => true]);

    seedPageAdSet($page->id, daily: 500.00);
    $adSetId = $page->id * 10 + 2;

    captureBudgets('2026-06-13');

    $history = BudgetSnapshot::where('entity_type', BudgetSnapshot::ENTITY_AD_SET)
        ->where('entity_id', $adSetId)
        ->where('date', '2026-06-13')
        ->first();

    expect($history)->not->toBeNull()
        ->and((float) $history->daily_budget)->toBe(500.00)
        ->and($history->effective_status)->toBe('ACTIVE')
        ->and($history->source)->toBe(BudgetSnapshot::SOURCE_CAPTURE);
});

it('keeps one row per ad set per date and overwrites it with the latest budget', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509302, 'auto_update_ad_budget' => true]);

    seedPageAdSet($page->id, daily: 500.00);
    $adSetId = $page->id * 10 + 2;

    captureBudgets('2026-06-13');

    // The budget is raised later the same day; the next run replaces the row.
    AdSet::where('id', $adSetId)->update(['daily_budget' => 750.00]);

    captureBudgets('2026-06-13');

    $histories = BudgetSnapshot::where('entity_type', BudgetSnapshot::ENTITY_AD_SET)
        ->where('entity_id', $adSetId)
        ->get();

    expect($histories)->toHaveCount(1)
        ->and((float) $histories->first()->daily_budget)->toBe(750.00);
});

it('keeps a separate row per date so history builds up', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509303, 'auto_update_ad_budget' => true]);

    seedPageAdSet($page->id, daily: 500.00);
    $adSetId = $page->id * 10 + 2;

    captureBudgets('2026-06-13');

    AdSet::where('id', $adSetId)->update(['daily_budget' => 750.00]);

    captureBudgets('2026-06-14');

    $history = AdSet::find($adSetId)->budgetSnapshots()->get();

    expect($history)->toHaveCount(2)
        // Relation orders newest first.
        ->and((float) $history[0]->daily_budget)->toBe(750.00)
        ->and((float) $history[1]->daily_budget)->toBe(500.00);
});

it('skips ad sets with no budget of their own (CBO)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($workspace)->create(['id' => 118273645509304, 'auto_update_ad_budget' => true]);

    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create(['id' => 900900920, 'meta_ads_account_id' => $account->id, 'name' => 'CBO', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => 750.00]);
    AdSet::create([
        'id' => 900900921,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'meta_page_id' => $page->id,
        'name' => 'CBO AS',
        'daily_budget' => null,
        'lifetime_budget' => null,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    captureBudgets('2026-06-13');

    expect(BudgetSnapshot::where('entity_id', 900900921)->exists())->toBeFalse();
});

it('ignores ad sets on ad accounts whose sync is switched off', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::firstOrCreate(['id' => 556], ['name' => 'Paused acct', 'active_sync' => false]);
    $campaign = Campaign::create(['id' => 900900930, 'meta_ads_account_id' => $account->id, 'name' => 'C', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
    AdSet::create([
        'id' => 900900931,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'AS',
        'daily_budget' => 500.00,
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    captureBudgets('2026-06-13');

    expect(BudgetSnapshot::where('entity_id', 900900931)->exists())->toBeFalse();
});

it('snapshots paused ad sets too, recording the status alongside the budget', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::firstOrCreate(['id' => 555], ['name' => 'Acct']);
    $campaign = Campaign::create(['id' => 900900940, 'meta_ads_account_id' => $account->id, 'name' => 'C', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
    AdSet::create([
        'id' => 900900941,
        'meta_ads_account_id' => $account->id,
        'meta_ads_campaign_id' => $campaign->id,
        'name' => 'Paused AS',
        'daily_budget' => 300.00,
        'status' => 'PAUSED',
        'effective_status' => 'PAUSED',
    ]);

    captureBudgets('2026-06-13');

    $history = BudgetSnapshot::where('entity_id', 900900941)->first();

    expect($history)->not->toBeNull()
        ->and((float) $history->daily_budget)->toBe(300.00)
        ->and($history->status)->toBe('PAUSED');
});
