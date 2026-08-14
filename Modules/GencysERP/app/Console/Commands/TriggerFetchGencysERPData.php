<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\GencysERP\Support\Fetchers\GencysFetcherFactory;

/**
 * Triggers the n8n webhook that scrapes Gencys ERP, for any combination of the
 * data types Artemis pulls.
 *
 * Replaces the three near-identical per-type commands. They shared the
 * production gate, the workspace eligibility query, webhook resolution and
 * sync-run bookkeeping, and drifted apart in small ways that mattered — the
 * daily sales flow honoured --sync while the other two silently ignored it, and
 * each resolved the shared n8n webhook URL slightly differently. One entry point
 * with a fetcher per type keeps the differences that are real (date defaults,
 * chunking, payload shape) and removes the ones that were accidents.
 */
class TriggerFetchGencysERPData extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-data
        {--type=* : Data type(s) to fetch: transaction_history, daily_sales_tracker, purchase_order. Omit for all three}
        {--date= : Fetch a single date in Y-m-d format. Shortcut that overrides --start-date/--end-date (transaction history and daily sales only)}
        {--start-date= : Start of the date range in Y-m-d format}
        {--end-date= : End of the date range in Y-m-d format}
        {--item=* : Limit to specific inventory item id(s); repeat or comma-separate. Omit for all active items}
        {--workspace=* : Limit to specific workspace id(s); repeat or comma-separate. Omit for every ERP-enabled workspace}
        {--batch= : Attach the sync runs opened here to an existing gencys_sync_batches row}
        {--delay= : Seconds between queued daily-sales calls (per-item types stagger by chunk instead)}
        {--without-delivered : Send an empty delivered PO list so n8n re-fetches every purchase order instead of skipping delivered ones}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Trigger the n8n webhook to fetch Gencys ERP data (transaction history, daily sales tracker, purchase orders)';

    public function handle(GencysFetcherFactory $fetchers): int
    {
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->warn('This command only runs on production. Re-run with --force to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        $types = $this->resolveTypes($fetchers->types());

        if ($types === []) {
            $this->error('No valid --type given. Valid types: '.implode(', ', $fetchers->types()));

            return self::FAILURE;
        }

        $options = $this->fetcherOptions();

        $workspaceIds = $this->idOption('workspace');
        $itemIds = $this->idOption('item');

        if ($itemIds !== []) {
            $this->info('Limiting to inventory item id(s): '.implode(', ', $itemIds));
        }

        $this->info(($this->option('sync') ? 'Sending' : 'Queueing').' Gencys ERP data: '.implode(', ', $types));

        $opened = 0;

        foreach ($types as $type) {
            $fetcher = $fetchers->for($type, $this, $options);

            if (empty($fetcher->webhookUrl())) {
                $this->error("  {$type}: n8n webhook URL is not configured. Set N8N_WEBHOOK_URL or pass --webhook=.");

                return self::FAILURE;
            }

            $workspaces = $fetcher->loadWorkspaces($this->eligibleWorkspaces($workspaceIds));

            if ($workspaces->isEmpty()) {
                $this->warn('  No workspaces found with ERP credentials and an API key.');

                continue;
            }

            try {
                $opened += $fetcher->dispatch($workspaces);
            } catch (\InvalidArgumentException $e) {
                $this->error("  {$type}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info(($this->option('sync') ? 'Sent' : 'Queued')." {$opened} sync run(s).");

        return self::SUCCESS;
    }

    /**
     * Workspaces wired for ERP automation: credentials configured and at least
     * one API key for the n8n callback to authenticate with.
     */
    private function eligibleWorkspaces(array $workspaceIds)
    {
        return Workspace::query()
            ->whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->when(! empty($workspaceIds), fn ($query) => $query->whereIn('id', $workspaceIds));
    }

    /** Everything the fetchers read, resolved once. */
    private function fetcherOptions(): array
    {
        return [
            'date' => $this->option('date'),
            'start-date' => $this->option('start-date'),
            'end-date' => $this->option('end-date'),
            'item' => $this->idOption('item'),
            'batch' => $this->option('batch'),
            'delay' => $this->option('delay'),
            'without-delivered' => (bool) $this->option('without-delivered'),
            'webhook' => $this->option('webhook'),
            'sync' => (bool) $this->option('sync'),
        ];
    }

    /**
     * @param  string[]  $valid  the types the factory can build
     * @return string[]
     */
    private function resolveTypes(array $valid): array
    {
        $requested = collect((array) $this->option('type'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($requested === []) {
            return $valid;
        }

        return array_values(array_intersect($requested, $valid));
    }

    /**
     * Parse an array id option into a list of ints. Accepts repeated flags
     * (--item=1 --item=2) and/or comma-separated values (--item=1,2).
     *
     * @return int[]
     */
    private function idOption(string $name): array
    {
        return collect((array) $this->option($name))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
