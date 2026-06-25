<?php

namespace App\Console\Commands;

use App\Jobs\FetchInventoryItemTransactionHistory;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'trigger-fetch-erp-transaction-history {--date= : The transaction history date in Y-m-d format (defaults to yesterday)} {--delay=30} {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP transaction history';

    public function handle()
    {
        $webhookUrl = config('services.n8n.transaction_history_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n transaction history webhook URL is not configured (services.n8n.transaction_history_webhook_url).');

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

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with(['apiKeys', 'inventoryItems' => function ($query)  {
                $query->where('is_active', true);
            }])
            ->get();

        $dispatched = 0;

        foreach ($workspaces as $workspace) {
            foreach ($workspace->inventoryItems as $item) {
                $callbackUrl = config('app.url')."/api/v1/public/inventory-items/$item->id/transactions/sync";

                $apiKey = $workspace->apiKeys->first();

                $data = [
                    'workspace_id' => $workspace->id,
                    'workspace_api_key' => $apiKey->reveal(),
                    'erp_username' => $workspace->erp_username,
                    'erp_password' => $workspace->erp_password,
                    'webhook_url' => $callbackUrl,
                    'inventory_item_id' => $item->id,
                    'keyword' => $item->sku,
                    'date' => $transactionDate,
                ];

                dispatch(new FetchInventoryItemTransactionHistory($webhookUrl, $data))
                    ->delay(now()->addSeconds($dispatched * 10));

                $dispatched++;
            }
        }
    }
}
