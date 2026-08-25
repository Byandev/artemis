<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/**
 * The one scheduled entry point for Gencys ERP syncing.
 *
 * It queues a batch per sync type and hands over — the batch queue drains them
 * one at a time, and each batch sends its next group of runs only once the
 * previous group has reported back. That's why this can run five times a day
 * without the passes trampling each other: a tick that lands while the morning's
 * work is still going simply joins the queue, and a tick that would duplicate a
 * batch nobody has started yet collapses into it instead.
 */
class SyncGencysErp extends Command
{
    /**
     * The order batches are queued in, which is the order they'll run.
     *
     * Kept as it was when each of these had its own schedule slot. Order matters
     * more now than it did then — the queue is serial, so a slow purchase-order
     * batch does hold up the daily sales tracker behind it. Pass --type to
     * change the order (the flags are honoured in the order given).
     */
    private const ORDER = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
    ];

    protected $signature = 'gencys-erp:sync
        {--type=* : Limit to specific sync type(s), in the order given. Omit for all of them}
        {--workspace= : Limit the batches to one workspace id. Omit to cover every ERP-connected workspace}
        {--sync : POST to n8n in-process instead of handing it to the erp queue worker (use this to hit a test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Queue the Gencys ERP sync batches (transaction history, purchase orders, daily sales tracker)';

    public function handle(BatchRunner $runner, SyncFlowRegistry $flows): int
    {
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        try {
            $types = $this->types($flows);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $workspaceId = $this->option('workspace') ? (int) $this->option('workspace') : null;
        $inline = (bool) $this->option('sync');

        // One batch carrying every type, so the whole pass is a single unit of
        // work: one thing to watch, one thing to cancel, and the types run in
        // the order listed because the runs are built in that order.
        $parameters = [];

        foreach ($types as $type) {
            $parameters[$type] = array_filter([
                ...$flows->for($type)->defaultParameters(),
                'inline' => $inline ?: null,
            ]);
        }

        $batch = $runner->queue(
            syncTypes: $types,
            parameters: $parameters,
            workspaceId: $workspaceId,
        );

        $labels = collect($types)->map(fn (string $type) => $flows->for($type)->label());

        if (! $batch->wasRecentlyCreated) {
            $this->info("An identical pass is already queued as batch #{$batch->id} — nothing new to add.");

            return self::SUCCESS;
        }

        if ($batch->total_runs === 0) {
            $this->warn('Nothing to sync: no workspace has ERP credentials, an API key and syncable records.');

            return self::SUCCESS;
        }

        $this->info("Queued batch #{$batch->id}: {$batch->total_runs} run(s) across ".$labels->count().' sync type(s).');

        foreach ($types as $type) {
            $this->line(sprintf(
                '  %-22s %d run(s)',
                $flows->for($type)->label(),
                $batch->runs()->where('sync_type', $type)->count(),
            ));
        }

        $this->newLine();
        $this->line($batch->status === GencysSyncBatch::STATUS_RUNNING
            ? 'Started — it holds the ERP until it finishes.'
            : 'Waiting its turn behind the batch already running.');
        $this->line('Watch it with: php artisan gencys-erp:sync-batches');

        return self::SUCCESS;
    }

    /**
     * The sync types to queue, in the order they should run.
     *
     * @return array<int, string>
     *
     * @throws \InvalidArgumentException
     */
    private function types(SyncFlowRegistry $flows): array
    {
        $requested = collect((array) $this->option('type'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return self::ORDER;
        }

        $unknown = $requested->reject(fn (string $type) => $flows->has($type));

        if ($unknown->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'Unknown sync type(s): '.$unknown->implode(', ').'. Available: '.implode(', ', $flows->types()).'.'
            );
        }

        return $requested->all();
    }
}
