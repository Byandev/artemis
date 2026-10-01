<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/**
 * Queues a transaction-history batch by hand. The schedule uses
 * `gencys-erp:sync` instead — this exists for re-running a specific date.
 */
class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-transaction-history
        {--date= : Sync a single transaction history date in Y-m-d format. Shortcut that overrides --start-date/--end-date}
        {--start-date= : Start of the date range in Y-m-d format}
        {--end-date= : End of the date range in Y-m-d format (defaults to today when --start-date is given)}
        {--item=* : Deprecated and ignored — one call now brings back every item on that ERP report}
        {--workspace= : Limit the batch to one workspace id. Omit to cover every ERP-connected workspace}
        {--delay= : Deprecated and ignored — the batch paces itself by waiting for each group to report back}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook in-process instead of handing it to the erp queue worker (use this to hit an n8n test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Queue a Gencys ERP batch that fetches transaction history (the schedule uses gencys-erp:sync)';

    public function handle(BatchRunner $runner, SyncFlowRegistry $flows): int
    {
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        if ($this->option('delay')) {
            $this->warn('--delay is ignored: the batch sends the next group only once the previous one reports back.');
        }

        if ($this->option('item')) {
            $this->warn('--item is ignored: the ERP report is read a date at a time and comes back with every item on it.');
        }

        $flow = $flows->for(GencysSyncRun::TYPE_TRANSACTION_HISTORY);

        try {
            $dates = $this->explicitDates();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // No date options at all means "whatever the scheduled sync would do".
        $dateParameters = $dates
            ? ['dates' => $dates->map(fn (Carbon $date) => $date->format('m/d/Y'))->all()]
            : $flow->defaultParameters();

        $batch = $runner->queue(
            syncTypes: [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
            parameters: [
                GencysSyncRun::TYPE_TRANSACTION_HISTORY => array_filter([
                    ...$dateParameters,
                    'webhook' => $this->option('webhook') ?: null,
                    'inline' => (bool) $this->option('sync') ?: null,
                ]),
            ],
            workspaceId: $this->option('workspace') ? (int) $this->option('workspace') : null,
        );

        $all = $batch->parametersFor(GencysSyncRun::TYPE_TRANSACTION_HISTORY)['dates'] ?? [];
        $rangeLabel = count($all) === 1 ? $all[0] : ($all[0] ?? '?').' – '.(end($all) ?: '?');

        if (! $batch->wasRecentlyCreated) {
            $this->info("An identical batch (#{$batch->id}) is already queued for {$rangeLabel} — nothing new to add.");

            return self::SUCCESS;
        }

        if ($batch->total_runs === 0) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $this->info("Queued batch #{$batch->id}: {$batch->total_runs} run(s) for {$rangeLabel}.");
        $this->line("Status: {$batch->status}. Watch it with: php artisan gencys-erp:sync-batches");

        return self::SUCCESS;
    }

    /**
     * The dates the operator asked for, or null when they gave none and the
     * flow's own default window should be used instead.
     *
     * --date is a shortcut for a single day and wins over the range options.
     * A --start-date on its own runs through to today.
     *
     * @return Collection<int, Carbon>|null
     *
     * @throws \InvalidArgumentException
     */
    private function explicitDates(): ?Collection
    {
        if ($single = $this->option('date')) {
            return collect([$this->parseDate($single, 'date')]);
        }

        $startOption = $this->option('start-date');
        $endOption = $this->option('end-date');

        if (! $startOption && ! $endOption) {
            return null;
        }

        $start = $startOption ? $this->parseDate($startOption, 'start-date') : Carbon::yesterday();
        $end = $endOption ? $this->parseDate($endOption, 'end-date') : Carbon::today();

        if ($start->gt($end)) {
            throw new \InvalidArgumentException(
                "Start date ({$start->toDateString()}) must not be after end date ({$end->toDateString()})."
            );
        }

        $dates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }

        return $dates;
    }

    /**
     * Parse a Y-m-d date option, throwing a friendly error on bad input.
     *
     * @throws \InvalidArgumentException
     */
    private function parseDate(string $value, string $option): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                "Invalid --{$option} '{$value}'. Expected format: Y-m-d (e.g. 2026-06-24)."
            );
        }
    }
}
