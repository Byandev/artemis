<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\BudgetSnapshot;

class CaptureBudgetSnapshotsCommand extends Command
{
    protected $signature = 'metaads:capture-budgets {--date= : Snapshot date (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Snapshot per-ad-set budgets and roll up active ad-set budgets per page.';

    public function handle(): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();

        $snapshotted = $this->snapshotAdSetBudgets($date);
        $this->info("Snapshotted budgets for {$snapshotted} ad set(s) on {$date}.");

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
            ->whereDate('meta_ads_sets.start_time', '<=', $date)
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
        $skipped = 0;

        foreach ($pageDailyBudgets as $metaPageId => $dailyBudget) {
            // A Pancake page's id is the FB page id (== meta_page_id), so it maps
            // straight to a local Page. Record the summed *daily* budget even when
            // it's 0 (e.g. lifetime-budget-only pages).
            if (($page = Page::find($metaPageId)) === null) {
                continue;
            }

            // Opt-in: only pages switched to Auto on the Pages screen are
            // overwritten. Every other page keeps the budget it was given.
            if (! $page->auto_update_ad_budget) {
                $skipped++;

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

        if ($skipped > 0) {
            $this->info("Left {$skipped} page(s) alone — auto update is off for them.");
        }

        return self::SUCCESS;
    }

    /**
     * Record each ad set's current budget as that date's snapshot, so budget
     * history survives the next sync overwriting meta_ads_sets in place.
     *
     * One row per ad set per date, latest write wins — the command runs several
     * times a day, so a date ends up holding the last budget seen that day.
     * Ad sets with no budget of their own are skipped: under CBO the budget
     * lives on the campaign and every ad-set budget is null, which would
     * otherwise write a row of nulls for each of them every day.
     *
     * These rows are `capture` source: actually observed, unlike the `backfill`
     * rows metaads:backfill-adset-budgets reconstructs for past dates. The table
     * is polymorphic (ad_set / campaign / page); only ad sets are captured.
     */
    private function snapshotAdSetBudgets(string $date): int
    {
        $now = Carbon::now();
        $count = 0;

        DB::table('meta_ads_sets')
            ->join('meta_ads_accounts', 'meta_ads_accounts.id', '=', 'meta_ads_sets.meta_ads_account_id')
            ->where('meta_ads_accounts.active_sync', true)
            ->where(function ($query) {
                $query->whereNotNull('meta_ads_sets.daily_budget')
                    ->orWhereNotNull('meta_ads_sets.lifetime_budget');
            })
            ->orderBy('meta_ads_sets.id')
            ->select([
                'meta_ads_sets.id',
                'meta_ads_sets.daily_budget',
                'meta_ads_sets.lifetime_budget',
                'meta_ads_sets.bid_strategy',
                'meta_ads_sets.status',
                'meta_ads_sets.effective_status',
            ])
            ->chunk(1000, function ($adSets) use ($date, $now, &$count) {
                $rows = [];

                foreach ($adSets as $adSet) {
                    $rows[] = [
                        'entity_type' => BudgetSnapshot::ENTITY_AD_SET,
                        'entity_id' => $adSet->id,
                        'date' => $date,
                        'daily_budget' => $adSet->daily_budget,
                        'lifetime_budget' => $adSet->lifetime_budget,
                        'bid_strategy' => $adSet->bid_strategy,
                        'status' => $adSet->status,
                        'effective_status' => $adSet->effective_status,
                        'source' => BudgetSnapshot::SOURCE_CAPTURE,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // A capture always wins over whatever is already on the date —
                // including a reconstructed backfill row for the same day.
                DB::table('meta_ads_budget_snapshots')->upsert(
                    $rows,
                    ['entity_type', 'entity_id', 'date'],
                    ['daily_budget', 'lifetime_budget', 'bid_strategy', 'status', 'effective_status', 'source', 'updated_at'],
                );

                $count += count($rows);
            });

        return $count;
    }
}
