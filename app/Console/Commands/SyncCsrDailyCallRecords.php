<?php

namespace App\Console\Commands;

use App\Jobs\SyncCsrDailyCallRecord;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncCsrDailyCallRecords extends Command
{
    protected $signature = 'sync:csr-daily-call-records
                            {--date= : Single target date in Y-m-d format. If omitted, the last --days days are dispatched (one job per day).}
                            {--days=14 : Number of trailing days to backfill when --date is not provided.}
                            {--workspace= : Limit to one workspace, by id or slug. Defaults to every workspace.}';

    protected $description = 'Dispatch SyncCsrDailyCallRecord jobs to aggregate per-CSR call metrics. Backfills the last 14 days by default.';

    public function handle(): int
    {
        $workspaceId = $this->targetWorkspaceId();

        if ($workspaceId === false) {
            return self::FAILURE;
        }

        $scope = $workspaceId === null ? '' : " for workspace {$workspaceId}";

        if ($this->option('date')) {
            $date = CarbonImmutable::parse($this->option('date'))->toDateString();
            SyncCsrDailyCallRecord::dispatch($date, $workspaceId);
            $this->info("Dispatched SyncCsrDailyCallRecord for {$date}{$scope}.");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $start = CarbonImmutable::yesterday();

        for ($i = 0; $i < $days; $i++) {
            $date = $start->subDays($i)->toDateString();
            SyncCsrDailyCallRecord::dispatch($date, $workspaceId);
        }

        $this->info("Dispatched {$days} SyncCsrDailyCallRecord job(s){$scope} covering {$start->subDays($days - 1)->toDateString()} → {$start->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * The workspace to rebuild, or null for all of them.
     *
     * Returns false when --workspace was given and names nothing — a silent
     * full-workspace rebuild is not what someone scoping a run asked for.
     */
    private function targetWorkspaceId(): int|false|null
    {
        $option = $this->option('workspace');

        if (! $option) {
            return null;
        }

        $id = Workspace::query()
            ->where(fn ($q) => $q->where('slug', $option)->orWhere('id', $option))
            ->value('id');

        if ($id === null) {
            $this->error("No workspace matches '{$option}'.");

            return false;
        }

        return (int) $id;
    }
}
