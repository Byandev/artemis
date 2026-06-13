<?php

namespace Modules\MetaAds\Jobs;

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
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

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?: Carbon::today()->toDateString();

        try {
            $count = 0;

            // Per-page rollup: ad sets already carry meta_page_id, so sum their
            // daily/lifetime budgets grouped straight by page — no need to walk
            // ads → creatives.
            $pageBudgets = DB::table('meta_ads_sets')
                ->whereNotNull('meta_page_id')
                ->groupBy('meta_page_id')
                ->selectRaw('meta_page_id, SUM(daily_budget) AS daily_budget, SUM(lifetime_budget) AS lifetime_budget')
                ->get();

            foreach ($pageBudgets as $row) {
                if (! $row->daily_budget && ! $row->lifetime_budget) {
                    continue;
                }

                BudgetSnapshot::updateOrCreate(
                    [
                        'entity_type' => BudgetSnapshot::ENTITY_PAGE,
                        'entity_id' => (int) $row->meta_page_id,
                        'date' => $date,
                    ],
                    [
                        'daily_budget' => $row->daily_budget,
                        'lifetime_budget' => $row->lifetime_budget,
                    ],
                );
                $count++;

                // Mirror the page's summed *daily* budget into the workspace-facing
                // page_daily_budget_records. A Pancake page's id is the FB page id
                // (== meta_page_id), so it maps straight to a local Page. Record it
                // even when the daily budget is 0 (e.g. lifetime-budget-only pages).
                if (($page = Page::find((int) $row->meta_page_id)) !== null) {
                    PageDailyBudgetRecord::updateOrCreate(
                        [
                            'workspace_id' => $page->workspace_id,
                            'page_id' => $page->id,
                            'date' => $date,
                        ],
                        ['budget' => $row->daily_budget ?? 0],
                    );
                }
            }
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
