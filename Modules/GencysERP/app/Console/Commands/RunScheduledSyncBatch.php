<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * The scheduled Gencys ERP sweep: fires transaction history, the daily sales
 * tracker and purchase orders for every ERP-enabled workspace as one tracked
 * batch. Runs at 09:30, 14:00 and 19:00.
 *
 * The point of going through here rather than scheduling the three commands
 * separately is the overlap guard. Each type's command hands its webhook to n8n
 * in milliseconds and returns; the scrape and its callback take minutes. So
 * `withoutOverlapping()` on the scheduler always sees a finished process and
 * happily fires the next slot on top of work that is still in flight — which is
 * what was stacking concurrent ERP logins and tripping the 429 rate limit.
 *
 * A batch stays `running` until its last callback lands, so this command can
 * see the real state and skip a workspace that hasn't caught up. The skip is
 * recorded as a `skipped` batch rather than passed over silently, because a
 * missing slot and a broken slot look identical otherwise.
 */
class RunScheduledSyncBatch extends Command
{
    protected $signature = 'gencys-erp:sync
        {--workspace=* : Limit to specific workspace id(s); repeat or comma-separate. Omit for every ERP-enabled workspace}
        {--type=* : Limit to specific sync types (transaction_history, daily_sales_tracker, purchase_order). Omit for all three}
        {--ignore-guard : Dispatch even when the workspace still has a batch running. Use for manual catch-up runs}
        {--sync : POST to n8n immediately in-process instead of queueing on the erp worker}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Run the scheduled Gencys ERP sync (transaction history + daily sales + purchase orders) as one tracked batch per workspace';

    /** Every type is fetched by the one command; --type picks the branch. */
    private const FETCH_COMMAND = 'gencys-erp:trigger-fetch-data';

    private const VALID_TYPES = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
    ];

    public function handle(): int
    {
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->warn('This command only runs on production. Re-run with --force to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        $types = $this->resolveTypes();

        if ($types === []) {
            $this->error('No valid sync types selected. Valid types: '.implode(', ', self::VALID_TYPES));

            return self::FAILURE;
        }

        $workspaces = $this->eligibleWorkspaces();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $this->info("Gencys ERP sync — {$workspaces->count()} workspace(s), types: ".implode(', ', $types));

        $dispatched = 0;
        $skipped = 0;

        foreach ($workspaces as $workspace) {
            // The guard: only a batch that is still waiting on callbacks blocks.
            // A partial or failed batch is finished — its runs are all resolved —
            // so the next slot should retry rather than stall behind it forever.
            if (! $this->option('ignore-guard') && $open = GencysSyncBatch::openFor($workspace->id)) {
                GencysSyncBatch::skip($workspace->id, $open);
                $skipped++;

                $this->warn("  ✗ {$workspace->name} (#{$workspace->id}) — skipped, batch #{$open->id} still running ({$open->outstandingRuns()} run(s) outstanding)");

                continue;
            }

            $batch = GencysSyncBatch::start($workspace->id, $types);
            $failedTypes = [];

            foreach ($types as $type) {
                // One type per call rather than all three at once, so a failure
                // in one doesn't abort the rest of the batch.
                $exitCode = $this->call(self::FETCH_COMMAND, array_filter([
                    '--type' => [$type],
                    '--workspace' => [$workspace->id],
                    '--batch' => $batch->id,
                    // The environment gate was already cleared above; without this
                    // the child command would refuse to run outside production.
                    '--force' => true,
                    '--sync' => (bool) $this->option('sync'),
                ]));

                if ($exitCode !== 0) {
                    $failedTypes[] = $type;
                }
            }

            // Picks up whatever the child commands opened. A batch that opened no
            // runs at all closes as completed here rather than hanging as running
            // and blocking every later slot for this workspace.
            $batch->refreshCounters();

            if ($failedTypes !== []) {
                $batch->forceFill([
                    'message' => 'Dispatch failed for: '.implode(', ', $failedTypes),
                ])->save();
            }

            $dispatched++;

            $this->info("  ✓ {$workspace->name} (#{$workspace->id}) — batch #{$batch->id}, {$batch->total_runs} run(s) opened".
                ($failedTypes !== [] ? ' ('.count($failedTypes).' type(s) failed to dispatch)' : ''));
        }

        $this->newLine();
        $this->info("Dispatched {$dispatched} batch(es), skipped {$skipped} workspace(s).");

        return self::SUCCESS;
    }

    /** @return string[] */
    private function resolveTypes(): array
    {
        $requested = collect((array) $this->option('type'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($requested === []) {
            return GencysSyncBatch::DEFAULT_SYNC_TYPES;
        }

        return array_values(array_intersect($requested, self::VALID_TYPES));
    }

    private function eligibleWorkspaces()
    {
        $workspaceIds = collect((array) $this->option('workspace'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Workspace::query()
            ->whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->when(! empty($workspaceIds), fn ($query) => $query->whereIn('id', $workspaceIds))
            ->orderBy('id')
            ->get();
    }
}
