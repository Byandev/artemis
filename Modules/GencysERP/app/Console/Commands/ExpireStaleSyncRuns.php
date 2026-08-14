<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Fails sync runs that never received a callback. When n8n can't log into the
 * ERP (or silently drops a chunk) the items are simply never posted back and
 * their runs sit pending forever — this turns that silence into a visible
 * failure so the Sync Health view (and its 24h KPIs) stay honest.
 *
 * Also reconciles batches, which is what keeps the overlap guard unstuck.
 */
class ExpireStaleSyncRuns extends Command
{
    protected $signature = 'gencys-erp:expire-stale-sync-runs
        {--hours=3 : Mark pending runs older than this many hours as failed}';

    protected $description = 'Fail Gencys ERP sync runs that never received a callback (silent failures)';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $stale = GencysSyncRun::query()
            ->pending()
            ->where('started_at', '<', now()->subHours($hours))
            // fail() recounts the parent batch; load it up front rather than
            // lazily per run.
            ->with('batch')
            ->get();

        $stale->each->fail("No callback received within {$hours}h (sync timed out)");

        $this->info("Marked {$stale->count()} stale sync run(s) as failed.");

        $this->info("Reconciled {$this->reconcileOpenBatches()} stuck batch(es).");

        return self::SUCCESS;
    }

    /**
     * Close batches sitting at `running` with nothing left to wait for.
     *
     * Every path that resolves a run already recounts its batch, so this should
     * find nothing. It exists because the cost of being wrong is asymmetric: a
     * batch stuck `running` blocks its workspace at every future slot, and that
     * silent deadlock is far worse than an extra hourly query.
     *
     * The age floor avoids racing the orchestrator, which opens a batch a moment
     * before its child commands create the runs.
     */
    private function reconcileOpenBatches(): int
    {
        $stuck = GencysSyncBatch::query()
            ->open()
            ->where('started_at', '<', now()->subMinutes(15))
            ->whereDoesntHave('runs', fn ($query) => $query->where('status', GencysSyncRun::STATUS_PENDING))
            ->get();

        $stuck->each->refreshCounters();

        return $stuck->count();
    }
}
