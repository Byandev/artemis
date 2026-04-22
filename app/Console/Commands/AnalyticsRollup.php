<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\AnalyticsRollup\MainRollupBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AnalyticsRollup extends Command
{
    protected $signature = 'analytics:rollup
                            {--date= : Specific date (Y-m-d) to rebuild}
                            {--from= : Start of date range (Y-m-d)}
                            {--to= : End of date range (Y-m-d)}
                            {--workspace= : Limit to a specific workspace ID}
                            {--trailing-days=14 : Default rebuild window when no date args given}';

    protected $description = 'Rebuild workspace_daily_metrics rollup rows for the given date range or trailing window.';

    public function handle(MainRollupBuilder $builder): int
    {
        [$from, $to] = $this->resolveRange();

        $this->info("Rolling up workspace_daily_metrics from {$from} to {$to}.");

        $query = Workspace::query()->select('id');
        if ($workspaceId = $this->option('workspace')) {
            $query->where('id', (int) $workspaceId);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(50, function ($workspaces) use ($builder, $from, $to, &$count) {
            foreach ($workspaces as $workspace) {
                $builder->forDateRange($workspace->id, $from, $to);
                $count++;
                if ($count % 10 === 0) {
                    $this->line("  processed {$count} workspace(s)");
                }
            }
        });

        $this->info("Done. Processed {$count} workspace(s).");

        return self::SUCCESS;
    }

    private function resolveRange(): array
    {
        if ($date = $this->option('date')) {
            $d = CarbonImmutable::parse($date)->toDateString();

            return [$d, $d];
        }

        $to = $this->option('to')
            ? CarbonImmutable::parse($this->option('to'))
            : CarbonImmutable::yesterday();

        $from = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))
            : $to->subDays((int) $this->option('trailing-days') - 1);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }
}
