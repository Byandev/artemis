<?php

namespace Modules\GencysERP\Jobs\Concerns;

use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Sync-run bookkeeping shared by the three Gencys fetch jobs.
 *
 * Each job carries the ids of the pending GencysSyncRun rows the trigger command
 * opened for its call ($syncRunIds) and owns the same two moments: stamping the
 * runs when the webhook actually goes out, and failing them if it never lands.
 */
trait TracksSyncRuns
{
    /**
     * Re-stamp started_at to the moment this call actually goes out.
     *
     * The runs are opened when the command dispatches, but chunks are staggered
     * by minutes so a run can sit in the queue long after that — and
     * gencys-erp:expire-stale-sync-runs measures its timeout from started_at.
     * Without this a late chunk burns part of its own grace period queued, and
     * a long enough sweep gets runs failed before n8n was even asked.
     */
    protected function markRunsStarted(): void
    {
        if (empty($this->syncRunIds)) {
            return;
        }

        GencysSyncRun::query()
            ->whereIn('id', $this->syncRunIds)
            ->pending()
            ->update(['started_at' => now()]);
    }

    /** Fail this call's pending runs when the outbound call never reaches n8n. */
    protected function failPendingRuns(string $message): void
    {
        if (empty($this->syncRunIds)) {
            return;
        }

        GencysSyncRun::query()
            ->whereIn('id', $this->syncRunIds)
            ->pending()
            ->get()
            ->each
            ->fail($message);
    }
}
