<?php

namespace App\Console\Commands;

use App\Jobs\Analytics\RebuildPageDailyMetricsJob;
use App\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RollupAnalytics extends Command
{
    protected $signature = 'analytics:rollup
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}
                            {--workspace= : Limit to a single workspace ID}
                            {--page= : Limit to a single page ID (requires --workspace)}';

    protected $description = 'Dispatch jobs to rebuild workspace_page_daily_metrics for a date.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $workspaceId = $this->option('workspace');
        $pageId = $this->option('page');

        $query = Page::query()->select('id', 'workspace_id');

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        if ($pageId) {
            $query->where('id', $pageId);
        }

        $count = 0;

        $query->orderBy('id')->chunkById(200, function ($pages) use ($date, &$count) {
            foreach ($pages as $page) {
                RebuildPageDailyMetricsJob::dispatch($page->workspace_id, $page->id, $date);
                $count++;
            }
        });

        $this->info("Dispatched {$count} rollup job(s) for {$date}.");

        return self::SUCCESS;
    }
}
