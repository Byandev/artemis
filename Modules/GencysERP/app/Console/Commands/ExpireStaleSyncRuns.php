<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Fails sync runs that never received a callback. When n8n can't log into the
 * ERP (or silently drops a chunk) the items are simply never posted back and
 * their runs sit pending forever — this turns that silence into a visible
 * failure so the Sync Health view (and its 24h KPIs) stay honest.
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
            // Runs inside a batch have their own, much tighter timeout and their
            // own retry policy — sweeping them here would fail runs the batch is
            // legitimately still waiting on. See BatchRunner::expireTimedOutRuns().
            ->whereNull('gencys_sync_batch_id')
            ->where('started_at', '<', now()->subHours($hours))
            ->get();

        $stale->each->fail("No callback received within {$hours}h (sync timed out)");

        $this->info("Marked {$stale->count()} stale sync run(s) as failed.");

        return self::SUCCESS;
    }
}
