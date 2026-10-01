<?php

namespace App\Console\Commands;

use App\Jobs\Analytics\BuildPageOrderReportBreakdownJob;
use App\Models\Workspace;
use App\Support\Analytics\PageOrderReportBreakdownBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Dispatches the rebuild of page_order_report_breakdown_daily_records — the per
 * page, per day breakdown of orders by the customer history they arrived with.
 *
 * Work is fanned out onto the analytics queue as one job per (workspace, date),
 * so a long backfill never blocks the scheduler and each job stays well inside
 * the worker timeout. --sync runs the same builder inline instead, which is what
 * you want for a deploy backfill you are watching, or on a box with no worker.
 */
class BuildPageOrderReportBreakdownDailyRecords extends Command
{
    protected $signature = 'build-page-order-report-breakdown-daily-records
        {--date= : Rebuild a single day (YYYY-MM-DD)}
        {--days=3 : Rebuild this many days back from today}
        {--from= : Start date (YYYY-MM-DD), overrides --days}
        {--to= : End date (YYYY-MM-DD), defaults to today}
        {--workspace= : Limit to a single workspace, by id or slug}
        {--sync : Rebuild inline instead of dispatching to the queue}';

    protected $description = 'Rebuild the daily breakdown of orders by customer order history';

    public function handle(PageOrderReportBreakdownBuilder $builder): int
    {
        if ($this->option('date')) {
            $from = $to = CarbonImmutable::parse($this->option('date'))->startOfDay();
        } else {
            $to = $this->option('to')
                ? CarbonImmutable::parse($this->option('to'))->startOfDay()
                : CarbonImmutable::today();

            $from = $this->option('from')
                ? CarbonImmutable::parse($this->option('from'))->startOfDay()
                : $to->subDays(max(0, (int) $this->option('days') - 1));
        }

        if ($from->gt($to)) {
            $this->error('--from must not be after --to.');

            return self::FAILURE;
        }

        $workspaceIds = $this->targetWorkspaceIds();

        if ($workspaceIds === false) {
            return self::FAILURE;
        }

        if ($workspaceIds === []) {
            $this->warn('No workspaces to rebuild.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %s → %s across %d workspace(s)',
            $this->option('sync') ? 'Rebuilding' : 'Dispatching',
            $from->toDateString(),
            $to->toDateString(),
            count($workspaceIds),
        ));

        return $this->option('sync')
            ? $this->rebuildInline($builder, $from, $to, $workspaceIds)
            : $this->dispatchJobs($from, $to, $workspaceIds);
    }

    /** @param  list<int>  $workspaceIds */
    private function dispatchJobs(CarbonImmutable $from, CarbonImmutable $to, array $workspaceIds): int
    {
        $dispatched = 0;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            foreach ($workspaceIds as $workspaceId) {
                BuildPageOrderReportBreakdownJob::dispatch($date->toDateString(), $workspaceId)
                    ->onQueue('analytics');
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} job(s) to the analytics queue.");

        return self::SUCCESS;
    }

    /** @param  list<int>  $workspaceIds */
    private function rebuildInline(
        PageOrderReportBreakdownBuilder $builder,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $workspaceIds,
    ): int {
        $days = $from->diffInDays($to) + 1;
        $bar = $this->output->createProgressBar($days * count($workspaceIds));
        $bar->start();

        $written = 0;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            foreach ($workspaceIds as $workspaceId) {
                $written += $builder->rebuild($date->toDateString(), $workspaceId);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Wrote {$written} rows.");

        return self::SUCCESS;
    }

    /**
     * The workspaces to rebuild.
     *
     * Returns false when --workspace was given and names nothing — a silent
     * full-workspace rebuild is not what someone scoping a run asked for.
     *
     * @return list<int>|false
     */
    private function targetWorkspaceIds(): array|false
    {
        $option = $this->option('workspace');

        if (! $option) {
            return Workspace::query()->orderBy('id')->pluck('id')->all();
        }

        $id = Workspace::query()
            ->where(fn ($q) => $q->where('slug', $option)->orWhere('id', $option))
            ->value('id');

        if ($id === null) {
            $this->error("No workspace matches '{$option}'.");

            return false;
        }

        return [(int) $id];
    }
}
