<?php

namespace App\Console\Commands;

use App\Jobs\TriggerFetchTransactionHistoryRecord;
use App\Models\Workspace;
use Illuminate\Console\Command;

class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'trigger-fetch-erp-transaction-history {--delay=30} {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP transaction history';

    public function handle()
    {
        $webhookUrl = config('services.n8n.transaction_history_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n transaction history webhook URL is not configured (services.n8n.transaction_history_webhook_url).');

            return 1;
        }

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with('apiKeys')
            ->get();

        $total = $workspaces->count();

        if ($total === 0) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $delay = (int) $this->option('delay');
        $sync = (bool) $this->option('sync');

        // URL n8n posts the synced ERP transaction history back to. Configurable so it
        // can point at a reachable host (local n8n, staging, tunnel) instead of being
        // hardcoded. Defaults to APP_URL when N8N_INVENTORY_SYNC_CALLBACK_URL isn't set.
//        $callbackBase = rtrim(config('services.n8n.inventory_sync_callback_url') ?: config('app.url'), '/');
//        $callbackUrl = "{$callbackBase}/api/v1/public/transaction-history/sync";
        $callbackUrl = "https://stretchy-wanetta-unwinning.ngrok-free.dev/api/v1/public/transaction-history/sync";

        $this->info($sync
            ? "Sending {$total} workspace(s) synchronously (no queue)"
            : "Dispatching {$total} workspace(s) with {$delay}s delay between jobs");

        $dispatched = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping workspace {$workspace->id} — no API key found.");

                continue;
            }

            $data = [
                'workspace_id' => $workspace->id,
                'workspace_api_key' => $apiKey->reveal(),
                // ERP login the n8n pipeline authenticates with (password decrypted).
                'erp_username' => $workspace->erp_username,
                'erp_password' => $workspace->erp_password,
                'webhook_url' => $callbackUrl,
            ];

            if ($sync) {
                TriggerFetchTransactionHistoryRecord::dispatchSync($webhookUrl, $data);
                $this->info("Sent for workspace {$workspace->id} — {$workspace->slug}");
            } else {
                $jobDelaySeconds = $dispatched * $delay;

                TriggerFetchTransactionHistoryRecord::dispatch($webhookUrl, $data)
                    ->delay(now()->addSeconds($jobDelaySeconds));

                $this->info("Dispatched for workspace {$workspace->id} — {$workspace->slug} (delay: {$jobDelaySeconds}s)");
            }

            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}");

        return 0;
    }
}
