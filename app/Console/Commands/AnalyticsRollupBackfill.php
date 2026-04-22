<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\AnalyticsRollup\CsrPosRollupBuilder;
use App\Support\AnalyticsRollup\CustomerFactsRollupBuilder;
use App\Support\AnalyticsRollup\ItemRollupBuilder;
use App\Support\AnalyticsRollup\LocationRollupBuilder;
use App\Support\AnalyticsRollup\MainRollupBuilder;
use App\Support\AnalyticsRollup\RiderRollupBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AnalyticsRollupBackfill extends Command
{
    protected $signature = 'analytics:rollup-backfill
                            {--from= : Earliest date (Y-m-d), required}
                            {--to= : Latest date (Y-m-d), defaults to yesterday}
                            {--workspace= : Limit to a specific workspace ID}
                            {--chunk-days=7 : Days per logging chunk}
                            {--only= : Comma-separated subset of builders to run (main,rider,item,location,csr_pos,customer_facts)}';

    protected $description = 'One-shot historical backfill of all workspace_daily_metrics rollup tables. Runs per workspace, per day, walking backward from --to to --from.';

    public function handle(
        MainRollupBuilder $main,
        RiderRollupBuilder $rider,
        ItemRollupBuilder $item,
        LocationRollupBuilder $location,
        CsrPosRollupBuilder $csrPos,
        CustomerFactsRollupBuilder $customerFacts,
    ): int {
        $all = [
            'main' => $main,
            'rider' => $rider,
            'item' => $item,
            'location' => $location,
            'csr_pos' => $csrPos,
            'customer_facts' => $customerFacts,
        ];

        $only = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : array_keys($all);

        $builders = array_intersect_key($all, array_flip($only));
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
        $workspaceQuery->chunkById(50, function ($workspaces) use ($builders, $from, $to, $chunkDays, &$workspaceCount) {
            foreach ($workspaces as $workspace) {
                $this->line("Workspace {$workspace->id}…");

                $cursor = CarbonImmutable::parse($to->toDateString());
                while ($cursor->gte($from)) {
                    $chunkEnd = $cursor;
                    $chunkStart = $cursor->subDays($chunkDays - 1);
                    if ($chunkStart->lt($from)) {
                        $chunkStart = $from;
                    }

                    foreach ($builders as $builder) {
                        $builder->forDateRange(
                            $workspace->id,
                            $chunkStart->toDateString(),
                            $chunkEnd->toDateString()
                        );
                    }

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
