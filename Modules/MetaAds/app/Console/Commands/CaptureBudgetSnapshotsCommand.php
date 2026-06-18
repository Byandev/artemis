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

        // Per-page rollup: ad sets already carry meta_page_id, so sum their
        // daily/lifetime budgets grouped straight by page — no need to walk
        // ads → creatives. Only count ad sets whose ad account still has sync
        // enabled, so paused/disconnected accounts don't inflate the snapshot.
        $pageBudgets = DB::table('meta_ads_sets')
            ->join('meta_ads_accounts', 'meta_ads_accounts.id', '=', 'meta_ads_sets.meta_ads_account_id')
            ->where('meta_ads_accounts.active_sync', true)
            ->whereNotNull('meta_ads_sets.meta_page_id')
            ->where('meta_ads_sets.effective_status', 'ACTIVE')
            ->groupBy('meta_ads_sets.meta_page_id')
            ->selectRaw('meta_ads_sets.meta_page_id AS meta_page_id, SUM(meta_ads_sets.daily_budget) AS daily_budget, SUM(meta_ads_sets.lifetime_budget) AS lifetime_budget')
            ->get();

        $count = 0;

        foreach ($pageBudgets as $row) {
            // A Pancake page's id is the FB page id (== meta_page_id), so it maps
            // straight to a local Page. Record the summed *daily* budget even when
            // it's 0 (e.g. lifetime-budget-only pages).
            if (($page = Page::find((int) $row->meta_page_id)) === null) {
                continue;
            }

            PageDailyBudgetRecord::updateOrCreate(
                [
                    'workspace_id' => $page->workspace_id,
                    'page_id' => $page->id,
                    'date' => $date,
                ],
                ['budget' => $row->daily_budget ?? 0],
            );

            $count++;
        }

        $this->info("Captured page daily budgets for {$count} page(s) on {$date}.");

        return self::SUCCESS;
    }
}
