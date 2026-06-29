<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
use Modules\GencysERP\Models\GencysSyncRun;

class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-transaction-history
        {--date= : The transaction history date in Y-m-d format (defaults to yesterday)}
        {--delay=10 : Seconds to stagger each queued workspace by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP transaction history';

    public function handle()
    {
        $webhookUrl = $this->option('webhook') ?: config('services.n8n.transaction_history_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n transaction history webhook URL is not configured (services.n8n.transaction_history_webhook_url). Pass --webhook= to override.');

            return 1;
        }

        $dateOption = $this->option('date');

        try {
            $date = $dateOption
                ? Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay()
                : Carbon::yesterday();
        } catch (\Exception $e) {
            $this->error("Invalid date '{$dateOption}'. Expected format: Y-m-d (e.g. 2026-06-24).");

            return 1;
        }

        $transactionDate = $date->format('m/d/Y');
        $sync = (bool) $this->option('sync');
        $delay = max(0, (int) $this->option('delay'));

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with(['apiKeys', 'inventoryItems' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $this->info(($sync ? 'Sending' : 'Queueing')." ERP transaction history for {$transactionDate}…");

        $dispatched = 0;
        $totalCount = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            // One payload per workspace carrying every item, so n8n logs into the ERP
            // once and loops the items reusing that session. This is what avoids the
            // per-item logins that were tripping the ERP's rate limit (429).
            $workspace->inventoryItems
                ->chunk(10)
                ->values()
                ->each(function ($chunk) use (&$dispatched, &$totalCount, $apiKey, $workspace, $transactionDate, $webhookUrl, $delay) {
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

                    $offset = $dispatched * $delay;

                    dispatch(new FetchInventoryItemTransactionHistory($webhookUrl, $data, $runIds->values()->all()))
                        ->delay(now()->addSeconds($offset));
                });
        }

        $this->newLine();
        $this->info(($sync ? 'Sent' : 'Queued')." {$totalCount} workspace(s).");

        return 0;
    }
}
