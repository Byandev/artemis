<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CaptureBudgetSnapshotsCommand extends Command
{
    protected $signature = 'metaads:capture-budgets {--date= : Snapshot date (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Roll up active ad-set budgets per page into page_daily_budget_records.';

    public function handle(): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();

        // A page's daily budget comes from two places, and Meta only ever uses
        // one of them per campaign:
        //   1. ABO — the budget sits on the ad set. Ad sets carry meta_page_id,
        //      so we sum straight by page — no need to walk ads → creatives.
        //   2. CBO — the budget sits on the campaign and is shared across its ad
        //      sets, which leaves every ad-set budget null. The campaign has no
        //      page of its own, so we resolve it to the page its active ad sets
        //      promote and count the shared budget once.
        // Only ad accounts whose sync is still enabled count, so paused/
        // disconnected accounts don't inflate the snapshot.
        $pageDailyBudgets = [];

        // 1. Ad-set-level (ABO) daily budgets, summed per page.
        $adSetBudgets = DB::table('meta_ads_sets')
            ->join('meta_ads_accounts', 'meta_ads_accounts.id', '=', 'meta_ads_sets.meta_ads_account_id')
            ->where('meta_ads_accounts.active_sync', true)
            ->whereNotNull('meta_ads_sets.meta_page_id')
            ->where('meta_ads_sets.effective_status', 'ACTIVE')
            ->groupBy('meta_ads_sets.meta_page_id')
            ->selectRaw('meta_ads_sets.meta_page_id AS meta_page_id, SUM(meta_ads_sets.daily_budget) AS daily_budget')
            ->get();

        foreach ($adSetBudgets as $row) {
            $metaPageId = (int) $row->meta_page_id;
            $pageDailyBudgets[$metaPageId] = ($pageDailyBudgets[$metaPageId] ?? 0) + (float) ($row->daily_budget ?? 0);
        }

        // 2. Campaign-level (CBO) daily budgets. Resolve each active campaign that
        //    carries its own daily budget to a single page via its active ad sets
        //    (MIN keeps it deterministic when a campaign spans more than one page)
        //    so the shared campaign budget is counted exactly once.
        $campaignPages = DB::table('meta_ads_sets')
            ->where('effective_status', 'ACTIVE')
            ->whereNotNull('meta_page_id')
            ->groupBy('meta_ads_campaign_id')
            ->selectRaw('meta_ads_campaign_id, MIN(meta_page_id) AS meta_page_id');

        $campaignBudgets = DB::table('meta_ads_campaigns')
            ->join('meta_ads_accounts', 'meta_ads_accounts.id', '=', 'meta_ads_campaigns.meta_ads_account_id')
            ->joinSub($campaignPages, 'campaign_page', 'campaign_page.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->where('meta_ads_accounts.active_sync', true)
            ->where('meta_ads_campaigns.effective_status', 'ACTIVE')
            ->where('meta_ads_campaigns.daily_budget', '>', 0)
            ->selectRaw('campaign_page.meta_page_id AS meta_page_id, meta_ads_campaigns.daily_budget AS daily_budget')
            ->get();

        foreach ($campaignBudgets as $row) {
            $metaPageId = (int) $row->meta_page_id;
            $pageDailyBudgets[$metaPageId] = ($pageDailyBudgets[$metaPageId] ?? 0) + (float) $row->daily_budget;
        }

        $count = 0;

        foreach ($pageDailyBudgets as $metaPageId => $dailyBudget) {
            // A Pancake page's id is the FB page id (== meta_page_id), so it maps
            // straight to a local Page. Record the summed *daily* budget even when
            // it's 0 (e.g. lifetime-budget-only pages).
            if (($page = Page::find($metaPageId)) === null) {
                continue;
            }

            PageDailyBudgetRecord::updateOrCreate(
                [
                    'workspace_id' => $page->workspace_id,
                    'page_id' => $page->id,
                    'date' => $date,
                ],
                ['budget' => $dailyBudget],
            );

            $count++;
        }

        $this->info("Captured page daily budgets for {$count} page(s) on {$date}.");

        return self::SUCCESS;
    }
}
