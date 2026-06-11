<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\BudgetSnapshot;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class CaptureBudgetSnapshots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Run on the dedicated Meta Ads Horizon queue (max 3 processes). */
    public $queue = 'meta-ads';

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?: Carbon::today()->toDateString();

        $run = SyncRun::start(
            entityType: 'budget_snapshots',
            scopeType: null,
            scopeId: null,
            meta: ['date' => $date],
        );

        try {
            $count = 0;

            // Ad sets — only those with a budget set (CBO ad sets inherit from
            // campaign and have null budget; skip those, the campaign row covers them).
            AdSet::query()
                ->whereNotNull('daily_budget')
                ->orWhereNotNull('lifetime_budget')
                ->chunkById(500, function ($adSets) use ($date, &$count) {
                    foreach ($adSets as $adSet) {
                        BudgetSnapshot::updateOrCreate(
                            [
                                'entity_type' => BudgetSnapshot::ENTITY_AD_SET,
                                'entity_id' => $adSet->id,
                                'date' => $date,
                            ],
                            [
                                'daily_budget' => $adSet->daily_budget,
                                'lifetime_budget' => $adSet->lifetime_budget,
                                'bid_strategy' => $adSet->bid_strategy,
                                'status' => $adSet->status,
                                'effective_status' => $adSet->effective_status,
                            ],
                        );
                        $count++;
                    }
                });

            // Campaigns with CBO (budget at campaign level).
            Campaign::query()
                ->whereNotNull('daily_budget')
                ->orWhereNotNull('lifetime_budget')
                ->chunkById(500, function ($campaigns) use ($date, &$count) {
                    foreach ($campaigns as $campaign) {
                        BudgetSnapshot::updateOrCreate(
                            [
                                'entity_type' => BudgetSnapshot::ENTITY_CAMPAIGN,
                                'entity_id' => $campaign->id,
                                'date' => $date,
                            ],
                            [
                                'daily_budget' => $campaign->daily_budget,
                                'lifetime_budget' => $campaign->lifetime_budget,
                                'bid_strategy' => $campaign->bid_strategy,
                                'status' => $campaign->status,
                                'effective_status' => $campaign->effective_status,
                            ],
                        );
                        $count++;
                    }
                });

            // Per-page rollup: for each FB page, sum daily/lifetime budgets of
            // distinct ad-sets that have at least one ad pointing to that page.
            // Path: Ad → Creative.meta_page_id; Ad.meta_ads_set_id → AdSet.budget.
            $pageAdSets = DB::table('meta_ads_ads as a')
                ->join('meta_ads_creatives as c', 'c.id', '=', 'a.meta_ads_creative_id')
                ->whereNotNull('c.meta_page_id')
                ->select('c.meta_page_id', 'a.meta_ads_set_id')
                ->distinct()
                ->get()
                ->groupBy('meta_page_id');

            foreach ($pageAdSets as $pageId => $rows) {
                $adSetIds = $rows->pluck('meta_ads_set_id')->all();

                $sums = DB::table('meta_ads_sets')
                    ->whereIn('id', $adSetIds)
                    ->selectRaw('SUM(daily_budget) AS daily_budget, SUM(lifetime_budget) AS lifetime_budget')
                    ->first();

                if (! $sums || (! $sums->daily_budget && ! $sums->lifetime_budget)) {
                    continue;
                }

                BudgetSnapshot::updateOrCreate(
                    [
                        'entity_type' => BudgetSnapshot::ENTITY_PAGE,
                        'entity_id' => (int) $pageId,
                        'date' => $date,
                    ],
                    [
                        'daily_budget' => $sums->daily_budget,
                        'lifetime_budget' => $sums->lifetime_budget,
                    ],
                );
                $count++;
            }

            $run->succeed($count, ['snapshot_count' => $count]);
        } catch (Throwable $e) {
            $run->fail($e);
            throw $e;
        }
    }
}
