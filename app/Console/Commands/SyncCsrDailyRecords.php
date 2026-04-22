<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\AnalyticsRollup\CsrPosRollupBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncCsrDailyRecords extends Command
{
    protected $signature = 'sync:csr-daily-records
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}
                            {--trailing-days=1 : Rebuild this many days back from the target date}';

    protected $description = 'Rebuild per-CSR POS rollup rows. Thin wrapper over the analytics rollup builder; prefer `analytics:rollup --only=csr_pos`.';

    public function handle(CsrPosRollupBuilder $builder): int
    {
        $target = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::yesterday();

        $trailing = max(1, (int) $this->option('trailing-days'));
        $from = $target->subDays($trailing - 1)->toDateString();
        $to = $target->toDateString();

        $this->info("Syncing POS CSR daily rollup from {$from} to {$to}.");

        $count = 0;
        Workspace::query()->select('id')->orderBy('id')->chunkById(50, function ($workspaces) use ($builder, $from, $to, &$count) {
            foreach ($workspaces as $workspace) {
                $builder->forDateRange($workspace->id, $from, $to);
                $count++;
            }
        });

        $this->info("Done. Processed {$count} workspace(s).");

        return self::SUCCESS;
    }
}
