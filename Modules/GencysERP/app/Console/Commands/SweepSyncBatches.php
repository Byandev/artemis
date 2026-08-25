<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Support\BatchRunner;

/**
 * The heartbeat behind the batch queue.
 *
 * Batches normally move themselves along — an n8n callback resolves a run and the
 * next group goes out. This catches everything that breaks that chain: a group
 * n8n never answered, a webhook handshake that failed and left runs waiting on a
 * backoff, a batch finished by a callback that arrived while another was mid-tick.
 * Without it a single silent ERP failure would park the whole queue until someone
 * noticed.
 */
class SweepSyncBatches extends Command
{
    protected $signature = 'gencys-erp:sweep-sync-batches';

    protected $description = 'Time out Gencys sync runs whose callback never arrived and move the batch queue forward';

    public function handle(BatchRunner $runner): int
    {
        [$retried, $failed] = $runner->expireTimedOutRuns();

        if ($retried || $failed) {
            $this->info("Timed out {$retried} run(s) for retry, failed {$failed}.");
        }

        $runner->tick();

        return self::SUCCESS;
    }
}
