<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PageDailyRecord;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Insight;
use Modules\Pancake\Models\Order;

/**
 * Build Artemis-source page performance rows, one per Pancake page per day,
 * combining:
 *   - Meta Ads:   ad spend + purchase value (= sales), attributed via
 *                 ad set → meta_page_id → page.
 *   - Pancake POS: orders + delivered/returned (count + amount), via page_id.
 *
 * Only non-Gencys-partner workspaces (they default to the Artemis source).
 * Writes to the unified page_daily_records table (source=artemis, page=Page).
 * Re-runnable — upserts on the natural key.
 */
class BuildDailyPagePerformanceCommand extends Command
{
    protected $signature = 'build-page-daily-performance
        {--date= : Build a single date (YYYY-MM-DD)}
        {--days=3 : Trailing window ending today when no --date (default 3)}';

    protected $description = 'Build Artemis-source daily performance (Pancake POS + Meta Ads) per page.';

    public function handle(): int
    {
        $dates = $this->resolveDates();
        $total = 0;

        Workspace::where('is_gencys_partner', false)
            ->with('pages')
            ->get()
            ->each(function (Workspace $workspace) use ($dates, &$total) {
                foreach ($dates as $date) {
                    foreach ($workspace->pages as $page) {
                        $this->buildForPageDate($workspace, $page, $date);
                        $total++;
                    }
                }
            });

        $this->info("Built {$total} page performance row(s) across ".count($dates).' day(s).');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function resolveDates(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'))->toDateString()];
        }

        $days = max(1, (int) $this->option('days'));
        $end = Carbon::today();

        return collect(range($days - 1, 0))
            ->map(fn (int $i) => $end->copy()->subDays($i)->toDateString())
            ->all();
    }

    private function buildForPageDate(Workspace $workspace, Page $page, string $date): int
    {
        $pageId = $page->getKey();

        $sales = Order::where('page_id', $pageId)
            ->whereDate('confirmed_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('final_amount');

        $orders = Order::where('page_id', $pageId)
            ->whereDate('confirmed_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $ad_spent = Insight::whereHas('adSet', fn ($query) => $query->whereHas('page', fn ($query) => $query->whereKey($pageId)))
            ->where('date', $date)
            ->sum('spend');

        $roas = $ad_spent > 0 ? $sales / $ad_spent : 0;

        $delivered = Order::where('page_id', $pageId)
            ->whereDate('delivered_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $delivered_amount = Order::where('page_id', $pageId)
            ->whereDate('delivered_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('final_amount');

        $returned = Order::where('page_id', $pageId)
            ->whereDate('returned_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $returned_amount = Order::where('page_id', $pageId)
            ->whereDate('returned_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('final_amount');

        PageDailyRecord::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'source' => PageDailyRecord::SOURCE_ARTEMIS,
                'page_type' => $page->getMorphClass(),
                'page_id' => $pageId,
                'date' => $date,
            ],
            [
                'orders' => $orders,
                'sales' => $sales,
                'delivered' => $delivered,
                'delivered_amount' => $delivered_amount,
                'returned' => $returned,
                'returned_amount' => $returned_amount,
                'ad_spent' => $ad_spent,
                'roas' => $roas,
            ],
        );

        return 1;
    }
}
