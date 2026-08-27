<?php

namespace Modules\GencysERP\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

class TriggerFetchDailySalesTrackerCommand extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-daily-sales-tracker
        {--date= : Fetch a single date}
        {--start-date= : Start of an inclusive date range (requires --end-date)}
        {--end-date= : End of an inclusive date range (requires --start-date)}
        {--workspace= : Limit the batch to one workspace id. Omit to cover every ERP-connected workspace}
        {--delay= : Deprecated and ignored — the batch paces itself by waiting for each run to report back}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook in-process instead of handing it to the erp queue worker (use this to hit an n8n test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Queue a Gencys ERP batch that fetches the daily sales tracker (the schedule uses gencys-erp:sync)';

    public function handle(BatchRunner $runner, SyncFlowRegistry $flows): int
    {
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        if ($this->option('delay')) {
            $this->warn('--delay is ignored: the batch sends the next run only once the previous one reports back.');
        }

        $flow = $flows->for(GencysSyncRun::TYPE_DAILY_SALES_TRACKER);

        try {
            $dates = $this->explicitDates();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // No date options at all means "whatever the scheduled sync would do".
        $dateParameters = $dates ? ['dates' => $dates] : $flow->defaultParameters();

        $batch = $runner->queue(
            syncTypes: [GencysSyncRun::TYPE_DAILY_SALES_TRACKER],
            parameters: [
                GencysSyncRun::TYPE_DAILY_SALES_TRACKER => array_filter([
                    ...$dateParameters,
                    'webhook' => $this->option('webhook') ?: null,
                    'inline' => (bool) $this->option('sync') ?: null,
                ]),
            ],
            workspaceId: $this->option('workspace') ? (int) $this->option('workspace') : null,
        );

        $all = $batch->parametersFor(GencysSyncRun::TYPE_DAILY_SALES_TRACKER)['dates'] ?? [];
        $dateLabel = count($all) === 1
            ? $all[0]
            : count($all).' dates: '.($all[0] ?? '?').' → '.(end($all) ?: '?');

        if (! $batch->wasRecentlyCreated) {
            $this->info("An identical batch (#{$batch->id}) is already queued for {$dateLabel} — nothing new to add.");

            return self::SUCCESS;
        }

        if ($batch->total_runs === 0) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $this->info("Queued batch #{$batch->id}: {$batch->total_runs} run(s) for {$dateLabel}.");
        $this->line("Status: {$batch->status}. Watch it with: php artisan gencys-erp:sync-batches");

        return self::SUCCESS;
    }

    /**
     * The dates the operator asked for, or null when they gave none and the
     * flow's own default window should be used instead.
     *
     * Supports a single --date or a --start-date/--end-date range (inclusive).
     *
     * @return array<int, string>|null
     *
     * @throws \InvalidArgumentException on invalid or inverted input
     */
    private function explicitDates(): ?array
    {
        $start = $this->option('start-date');
        $end = $this->option('end-date');

        // Range mode: both bounds required so the intent is unambiguous.
        if ($start || $end) {
            if (! $start || ! $end) {
                throw new \InvalidArgumentException('Both --start-date and --end-date must be provided for a date range.');
            }

            try {
                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->startOfDay();
            } catch (\Exception) {
                throw new \InvalidArgumentException("Invalid date range provided: {$start} → {$end}");
            }

            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException("--start-date ({$start}) must not be after --end-date ({$end}).");
            }

            $dates = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dates[] = $date->format('m/d/Y');
            }

            return $dates;
        }

        // Explicit single date.
        if ($this->option('date')) {
            try {
                $date = Carbon::parse($this->option('date'));
            } catch (\Exception) {
                throw new \InvalidArgumentException("Invalid date provided: {$this->option('date')}");
            }

            return [$date->format('m/d/Y')];
        }

        return null;
    }
}
