<?php

namespace App\Console\Commands;

use App\Jobs\Analytics\RebuildPageDailyMetricsJob;
use App\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RollupAnalytics extends Command
{
    protected $signature = 'analytics:rollup
                            {--date= : Single target date in Y-m-d format (defaults to yesterday). Mutually exclusive with --from/--to.}
                            {--from= : Start date in Y-m-d format (inclusive). Use with --to to backfill a range.}
                            {--to= : End date in Y-m-d format (inclusive). Use with --from to backfill a range.}
                            {--workspace= : Limit to a single workspace ID}
                            {--page= : Limit to a single page ID (requires --workspace)}';

    protected $description = 'Dispatch jobs to rebuild workspace_page_daily_metrics for a date or date range.';

    public function handle(): int
    {
        $dates = $this->resolveDates();

        if ($dates === null) {
            return self::FAILURE;
        }

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

        $query->orderBy('id')->chunkById(200, function ($pages) use ($dates, &$count) {
            foreach ($pages as $page) {
                foreach ($dates as $date) {
                    RebuildPageDailyMetricsJob::dispatch($page->workspace_id, $page->id, $date)->onQueue('analytics');
                    $count++;
                }
            }
        });

        $rangeLabel = count($dates) === 1
            ? $dates[0]
            : $dates[0].' to '.$dates[count($dates) - 1].' ('.count($dates).' days)';

        $this->info("Dispatched {$count} rollup job(s) for {$rangeLabel}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>|null Array of Y-m-d strings, or null on validation failure.
     */
    private function resolveDates(): ?array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date && ($from || $to)) {
            $this->error('Cannot use --date together with --from/--to.');

            return null;
        }

        if (($from && ! $to) || ($to && ! $from)) {
            $this->error('--from and --to must be provided together.');

            return null;
        }

        if ($from && $to) {
            $start = CarbonImmutable::parse($from);
            $end = CarbonImmutable::parse($to);

            if ($end->lt($start)) {
                $this->error('--to must be on or after --from.');

                return null;
            }

            $dates = [];
            $cursor = $start;
            while ($cursor->lte($end)) {
                $dates[] = $cursor->toDateString();
                $cursor = $cursor->addDay();
            }

            return $dates;
        }

        $single = $date
            ? CarbonImmutable::parse($date)->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        return [$single];
    }
}
