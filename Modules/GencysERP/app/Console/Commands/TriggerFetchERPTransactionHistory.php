<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
use Modules\GencysERP\Models\GencysSyncRun;

class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-transaction-history
        {--date= : Sync a single transaction history date in Y-m-d format. Shortcut that overrides --start-date/--end-date}
        {--start-date= : Start of the date range in Y-m-d format (defaults to yesterday)}
        {--end-date= : End of the date range in Y-m-d format (defaults to today)}
        {--item=* : Limit to specific inventory item id(s); repeat (--item=1 --item=2) or comma-separate (--item=1,2). Omit for all active items}
        {--delay=180 : Seconds to stagger each queued workspace by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP transaction history';

    public function handle()
    {
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->warn('This command only runs on production. Re-run with --force to override (current environment: '.app()->environment().').');

            return 0;
        }

        $webhookUrl = $this->option('webhook') ?: config('services.n8n.transaction_history_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n transaction history webhook URL is not configured (services.n8n.transaction_history_webhook_url). Pass --webhook= to override.');

            return 1;
        }

        try {
            $dates = $this->resolveDates();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $sync = (bool) $this->option('sync');
        $delay = max(0, (int) $this->option('delay'));
        $itemIds = $this->itemIds();

        if (! empty($itemIds)) {
            $this->info('Limiting to inventory item id(s): '.implode(', ', $itemIds));
        }

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with(['apiKeys', 'inventoryItems' => function ($query) use ($itemIds) {
                // Parent items are grouping placeholders with no ERP SKU — never sync them.
                $query->where('is_parent', false);

                // A specific --item selection wins over the active-only default so a
                // single item can be re-synced (or tested) even when it's inactive.
                if (empty($itemIds)) {
                    $query->where('is_active', true);
                } else {
                    $query->whereIn('id', $itemIds);
                }
            }])
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $rangeLabel = $dates->count() === 1
            ? $dates->first()->format('m/d/Y')
            : $dates->first()->format('m/d/Y').' – '.$dates->last()->format('m/d/Y');

        $this->info(($sync ? 'Sending' : 'Queueing')." ERP transaction history for {$rangeLabel}…");

        $dispatched = 0;
        $totalCount = 0;

        foreach ($dates as $date) {
            $transactionDate = $date->format('m/d/Y');

            foreach ($workspaces as $workspace) {
                $apiKey = $workspace->apiKeys->first();

                // One payload per workspace carrying every item, so n8n logs into the ERP
                // once and loops the items reusing that session. This is what avoids the
                // per-item logins that were tripping the ERP's rate limit (429).
                $workspace->inventoryItems
                    ->chunk(20)
                    ->values()
                    ->each(function ($chunk) use (&$dispatched, &$totalCount, $apiKey, $workspace, $transactionDate, $webhookUrl) {
                        $dispatched++;
                        $totalCount += count($chunk);

                        $callbackBase = rtrim(config('app.url'), '/');

                        // Open a pending sync run per item, keyed by item id. We hand
                        // each run's id to n8n (sync_run_id) so it can echo it back on
                        // the callback for an exact match; items that never report back
                        // stay pending until the stale-run sweeper fails them.
                        $runIds = $chunk->mapWithKeys(fn ($item) => [
                            $item->id => GencysSyncRun::start(
                                $workspace->id,
                                $item->id,
                                GencysSyncRun::TYPE_TRANSACTION_HISTORY,
                                ['date' => $transactionDate],
                            )->id,
                        ]);

                        $data = [
                            'workspace_id' => $workspace->id,
                            'workspace_api_key' => $apiKey->reveal(),
                            'erp_username' => $workspace->erp_username,
                            'erp_password' => $workspace->erp_password,
                            'date' => $transactionDate,
                            'webhook_url' => "{$callbackBase}/api/v1/public/inventory-items/transactions/bulk-sync",
                            'items' => $chunk->map(fn ($item) => [
                                'id' => $item->id,
                                'keyword' => $item->sku,
                                'sync_run_id' => $runIds[$item->id],
                            ])->values()->toArray(),
                        ];

                        dispatch(new FetchInventoryItemTransactionHistory($webhookUrl, $data, $runIds->values()->all()))
                            ->delay(now()->addMinutes(($dispatched - 1) * 2));
                    });
            }
        }

        $this->newLine();
        $this->info(($sync ? 'Sent' : 'Queued')." {$totalCount} workspace(s).");

        return 0;
    }

    /**
     * Resolve the inclusive list of dates to sync.
     *
     * --date is a shortcut for a single day and wins over the range options.
     * Otherwise the range runs from --start-date (default: yesterday) through
     * --end-date (default: today).
     *
     * @return Collection<int, Carbon>
     *
     * @throws \InvalidArgumentException
     */
    private function resolveDates(): Collection
    {
        if ($single = $this->option('date')) {
            return collect([$this->parseDate($single, 'date')]);
        }

        $start = ($startOption = $this->option('start-date'))
            ? $this->parseDate($startOption, 'start-date')
            : Carbon::yesterday();

        $end = ($endOption = $this->option('end-date'))
            ? $this->parseDate($endOption, 'end-date')
            : Carbon::today();

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
