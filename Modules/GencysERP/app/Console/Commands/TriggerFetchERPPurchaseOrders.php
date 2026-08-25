<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

class TriggerFetchERPPurchaseOrders extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-purchase-orders
        {--start-date= : Start of the PO date range in Y-m-d format}
        {--end-date= : End of the PO date range in Y-m-d format (defaults to today when --start-date is given)}
        {--item=* : Limit to specific inventory item id(s); repeat (--item=1 --item=2) or comma-separate (--item=1,2). Omit for all active items}
        {--workspace= : Limit the batch to one workspace id. Omit to cover every ERP-connected workspace}
        {--delay= : Deprecated and ignored — the batch paces itself by waiting for each group to report back}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook in-process instead of handing it to the erp queue worker (use this to hit an n8n test-mode webhook)}
        {--without-delivered : Send an empty delivered_purchase_orders_no array so n8n re-fetches every PO instead of skipping delivered ones}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Queue a Gencys ERP batch that fetches purchase orders (the schedule uses gencys-erp:sync)';

    public function handle(BatchRunner $runner, SyncFlowRegistry $flows): int
    {
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        if ($this->option('delay')) {
            $this->warn('--delay is ignored: the batch sends the next group only once the previous one reports back.');
        }

        $flow = $flows->for(GencysSyncRun::TYPE_PURCHASE_ORDER);

        // No date options at all means "whatever the scheduled sync would do".
        if (! $this->option('start-date') && ! $this->option('end-date')) {
            $range = $flow->defaultParameters();
        } else {
            try {
                $startDate = $this->option('start-date')
                    ? Carbon::createFromFormat('Y-m-d', $this->option('start-date'))->startOfDay()
                    : Carbon::now()->subMonths(3)->startOfDay();

                $endDate = $this->option('end-date')
                    ? Carbon::createFromFormat('Y-m-d', $this->option('end-date'))->startOfDay()
                    : Carbon::today();
            } catch (\Exception $e) {
                $this->error('Invalid date. Expected format: Y-m-d (e.g. 2026-06-24).');

                return self::FAILURE;
            }

            if ($startDate->greaterThan($endDate)) {
                $this->error("Start date ({$startDate->format('Y-m-d')}) cannot be after end date ({$endDate->format('Y-m-d')}).");

                return self::FAILURE;
            }

            $range = [
                'start_date' => $startDate->format('m/d/Y'),
                'end_date' => $endDate->format('m/d/Y'),
            ];
        }

        $rangeLabel = $range['start_date'].' – '.$range['end_date'];

        $batch = $runner->queue(
            syncTypes: [GencysSyncRun::TYPE_PURCHASE_ORDER],
            parameters: [
                GencysSyncRun::TYPE_PURCHASE_ORDER => array_filter([
                    ...$range,
                    'item_ids' => $this->itemIds(),
                    'without_delivered' => (bool) $this->option('without-delivered') ?: null,
                    'webhook' => $this->option('webhook') ?: null,
                    'inline' => (bool) $this->option('sync') ?: null,
                ]),
            ],
            workspaceId: $this->option('workspace') ? (int) $this->option('workspace') : null,
        );

        if (! $batch->wasRecentlyCreated) {
            $this->info("An identical batch (#{$batch->id}) is already queued for {$rangeLabel} — nothing new to add.");

            return self::SUCCESS;
        }

        if ($batch->total_runs === 0) {
            $this->warn('No workspaces found with ERP credentials, an API key and syncable items.');

            return self::SUCCESS;
        }

        $this->info("Queued batch #{$batch->id}: {$batch->total_runs} run(s) for {$rangeLabel}.");
        $this->line("Status: {$batch->status}. Watch it with: php artisan gencys-erp:sync-batches");

        return self::SUCCESS;
    }

    /**
     * Parse the --item option into a list of inventory item ids. Accepts repeated
     * flags (--item=1 --item=2) and/or comma-separated values (--item=1,2).
     *
     * @return int[]
     */
    private function itemIds(): array
    {
        return collect((array) $this->option('item'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
