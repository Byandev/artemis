<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/** A quick look at the ERP sync queue from the terminal. */
class ListSyncBatches extends Command
{
    protected $signature = 'gencys-erp:sync-batches
        {--limit=15 : How many batches to show}
        {--runs : Also break the newest batch down by run status}';

    protected $description = 'Show recent Gencys ERP sync batches and where the queue is up to';

    public function handle(): int
    {
        $batches = GencysSyncBatch::query()
            ->latest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No sync batches yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Types', 'Status', 'Progress', 'OK', 'Failed', 'Source', 'Queued', 'Finished'],
            $batches->map(fn (GencysSyncBatch $batch) => [
                $batch->id,
                implode(', ', (array) $batch->sync_types),
                $batch->status,
                $batch->progressPercent().'% of '.$batch->total_runs,
                $batch->succeeded_runs,
                $batch->failed_runs,
                $batch->source,
                $batch->queued_at?->diffForHumans() ?? '—',
                $batch->finished_at?->diffForHumans() ?? '—',
            ])->all(),
        );

        if ($this->option('runs')) {
            $this->breakDown($batches->first());
        }

        return self::SUCCESS;
    }

    private function breakDown(GencysSyncBatch $batch): void
    {
        $counts = $batch->runs()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->newLine();
        $this->line("Batch #{$batch->id} runs:");

        // A batch usually covers several sync types, so show the split too.
        foreach ((array) $batch->sync_types as $syncType) {
            $this->line(sprintf(
                '  %-22s %d',
                $syncType,
                $batch->runs()->where('sync_type', $syncType)->count(),
            ));
        }

        $this->newLine();

        foreach ([
            GencysSyncRun::STATUS_QUEUED,
            GencysSyncRun::STATUS_PENDING,
            GencysSyncRun::STATUS_SUCCESS,
            GencysSyncRun::STATUS_FAILED,
            GencysSyncRun::STATUS_CANCELLED,
        ] as $status) {
            $this->line(sprintf('  %-10s %d', $status, $counts[$status] ?? 0));
        }
    }
}
