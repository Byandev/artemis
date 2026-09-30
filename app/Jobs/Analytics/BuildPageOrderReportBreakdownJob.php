<?php

namespace App\Jobs\Analytics;

use App\Support\Analytics\PageOrderReportBreakdownBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds one workspace's page_order_report_breakdown_daily_records for one day.
 *
 * The rollup is fanned out per (workspace, date) rather than run as a single
 * job per day so each unit of work stays inside the analytics worker's timeout
 * and the nightly window parallelises across workers.
 *
 * Safe to run twice: the builder rebuilds the slice wholesale in a transaction.
 * ShouldBeUnique only avoids the wasted work when a manual backfill overlaps
 * the scheduled run — it is not what makes the job correct.
 */
class BuildPageOrderReportBreakdownJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Long enough to cover a retry storm, short enough that a lost lock frees itself. */
    public int $uniqueFor = 3600;

    /**
     * Just under the analytics worker's own 120s timeout, so a slice that runs
     * long fails as a job — retried, then visible in failed_jobs — rather than
     * being hard-killed mid-transaction by the worker.
     */
    public int $timeout = 110;

    /**
     * @param  string  $date  Y-m-d
     * @param  int|null  $workspaceId  Limit the rebuild to one workspace; null covers every one.
     */
    public function __construct(
        public string $date,
        public ?int $workspaceId = null,
    ) {}

    public function handle(PageOrderReportBreakdownBuilder $builder): void
    {
        $builder->rebuild($this->date, $this->workspaceId);
    }

    public function uniqueId(): string
    {
        return ($this->workspaceId ?? 'all').':'.$this->date;
    }
}
