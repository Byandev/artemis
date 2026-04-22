<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\AnalyticsRollup\MainRollupBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AnalyticsRollupBackfill extends Command
{
    protected $signature = 'analytics:rollup-backfill
                            {--from= : Earliest date (Y-m-d), required}
                            {--to= : Latest date (Y-m-d), defaults to yesterday}
                            {--workspace= : Limit to a specific workspace ID}
                            {--chunk-days=7 : Days per logging chunk}';

    protected $description = 'One-shot historical backfill of workspace_daily_metrics. Runs per workspace, per day, walking backward from --to to --from.';

    public function handle(MainRollupBuilder $builder): int
    {
        if (! $this->option('from')) {
            $this->error('--from is required.');

            return self::INVALID;
        }

        $from = CarbonImmutable::parse($this->option('from'));
        $to = $this->option('to')
            ? CarbonImmutable::parse($this->option('to'))
            : CarbonImmutable::yesterday();

        if ($from->gt($to)) {
            $this->error("--from ({$from->toDateString()}) is after --to ({$to->toDateString()}).");

            return self::INVALID;
        }

        $chunkDays = max(1, (int) $this->option('chunk-days'));
        $totalDays = $from->diffInDays($to) + 1;

        $this->info("Backfilling from {$from->toDateString()} to {$to->toDateString()} ({$totalDays} day(s)).");

        $workspaceQuery = Workspace::query()->select('id')->orderBy('id');
        if ($workspaceId = $this->option('workspace')) {
            $workspaceQuery->where('id', (int) $workspaceId);
        }

        $workspaceCount = 0;
        $workspaceQuery->chunkById(50, function ($workspaces) use ($builder, $from, $to, $chunkDays, &$workspaceCount) {
            foreach ($workspaces as $workspace) {
                $this->line("Workspace {$workspace->id}…");

                $cursor = CarbonImmutable::parse($to->toDateString());
                while ($cursor->gte($from)) {
                    $chunkEnd = $cursor;
                    $chunkStart = $cursor->subDays($chunkDays - 1);
                    if ($chunkStart->lt($from)) {
                        $chunkStart = $from;
                    }

                    $builder->forDateRange(
                        $workspace->id,
                        $chunkStart->toDateString(),
                        $chunkEnd->toDateString()
                    );

                    $this->line("  workspace={$workspace->id} {$chunkStart->toDateString()} → {$chunkEnd->toDateString()} done");

                    $cursor = $chunkStart->subDay();
                }

                $workspaceCount++;
            }
        });

        $this->info("Backfill complete. Processed {$workspaceCount} workspace(s).");

        return self::SUCCESS;
    }
}
