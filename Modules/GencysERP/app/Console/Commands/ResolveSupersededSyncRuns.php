<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Applies the "a success clears the earlier attempts" rule to history.
 *
 * New callbacks resolve their own predecessors as they arrive
 * (GencysSyncRun::resolveEarlierRunsWithSameParameters), but runs that were
 * already stuck pending/failed before that existed stay stuck. This sweeps them:
 * any pending/failed run that a later run with the same parameters has since
 * succeeded for is marked success too.
 */
class ResolveSupersededSyncRuns extends Command
{
    protected $signature = 'gencys-erp:resolve-superseded-sync-runs
        {--days= : Only consider runs started within this many days (default: all history)}
        {--dry-run : Report what would be resolved without writing anything}';

    protected $description = 'Mark pending/failed Gencys ERP sync runs as succeeded when a later run fetched the same data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = $this->option('days') !== null ? max(1, (int) $this->option('days')) : null;

        $since = $days ? now()->subDays($days) : null;

        // The latest success per parameter signature — the run that supersedes.
        $successBySignature = [];

        GencysSyncRun::query()
            ->where('status', GencysSyncRun::STATUS_SUCCESS)
            ->when($since, fn ($query) => $query->where('started_at', '>=', $since))
            ->orderBy('id')
            ->chunkById(1000, function ($runs) use (&$successBySignature) {
                foreach ($runs as $run) {
                    // Later ids overwrite earlier ones, so each key keeps the newest.
                    $successBySignature[$this->signatureFor($run)] = $run;
                }
            });

        if (empty($successBySignature)) {
            $this->info('No successful runs to match against.');

            return self::SUCCESS;
        }

        $resolved = 0;
        $scanned = 0;

        // Batches owning the runs we resolve. These writes go through forceFill
        // rather than GencysSyncRun::fail()/succeedById(), so nothing refreshes
        // the parent batch on our behalf — without recounting at the end a batch
        // would stay `running` with no outstanding runs, and the overlap guard
        // would block its workspace from ever dispatching again.
        $touchedBatchIds = [];

        GencysSyncRun::query()
            ->whereIn('status', [GencysSyncRun::STATUS_PENDING, GencysSyncRun::STATUS_FAILED])
            ->when($since, fn ($query) => $query->where('started_at', '>=', $since))
            ->orderBy('id')
            ->chunkById(500, function ($runs) use ($successBySignature, $dryRun, &$resolved, &$scanned, &$touchedBatchIds) {
                foreach ($runs as $run) {
                    $scanned++;

                    $success = $successBySignature[$this->signatureFor($run)] ?? null;

                    // Only a *later* success counts — an older one ran before this
                    // attempt's data existed.
                    if (! $success || $success->id <= $run->id) {
                        continue;
                    }

                    $resolved++;

                    if ($dryRun) {
                        $this->line("  run #{$run->id} ({$run->sync_type}, {$run->status}) → resolved by #{$success->id}");

                        continue;
                    }

                    $run->forceFill([
                        'status' => GencysSyncRun::STATUS_SUCCESS,
                        'rows_received' => $success->rows_received,
                        'rows_saved' => $success->rows_saved,
                        'finished_at' => now(),
                        'message' => "Resolved by sync run #{$success->id}, which fetched the same data.",
                    ])->save();

                    if ($run->batch_id) {
                        $touchedBatchIds[$run->batch_id] = true;
                    }
                }
            });

        if (! $dryRun && $touchedBatchIds !== []) {
            GencysSyncBatch::query()
                ->whereKey(array_keys($touchedBatchIds))
                ->get()
                ->each
                ->refreshCounters();
        }

        $this->info($dryRun
            ? "Would resolve {$resolved} of {$scanned} pending/failed run(s)."
            : "Resolved {$resolved} of {$scanned} pending/failed run(s).");

        return self::SUCCESS;
    }

    /** Workspace + sync type + item + parameters, as one comparable key. */
    private function signatureFor(GencysSyncRun $run): string
    {
        return implode('|', [
            $run->workspace_id,
            $run->sync_type,
            $run->inventory_item_id ?? '-',
            GencysSyncRun::parameterSignature($run->meta),
        ]);
    }
}
