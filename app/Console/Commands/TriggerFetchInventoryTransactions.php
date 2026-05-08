<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TriggerFetchInventoryTransactions extends Command
{
    protected $signature = 'trigger-fetch-inventory-transactions';

    protected $description = 'Trigger n8n webhook for each workspace to fetch inventory transactions';

    public function handle(): int
    {
        $webhookUrl = config('services.n8n.inventory_transaction_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n inventory transaction webhook URL is not configured (services.n8n.inventory_transaction_webhook_url).');

            return 1;
        }

        $workspaces = Workspace::whereHas('apiKeys')
            ->with('apiKeys')
            ->orderBy('id')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with API keys.');

            return 0;
        }

        $this->info("Triggering {$workspaces->count()} workspace(s)...");

        $success = 0;
        $failed = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            $data = [
                'workspace_id' => $workspace->id,
                'workspace_api_key' => $apiKey->reveal(),
                'callback_url' => 'https://stretchy-wanetta-unwinning.ngrok-free.dev/api/v1/public/inventory-transactions/sync',
            ];

            $response = Http::timeout(30)->post($webhookUrl, $data);

            if ($response->successful()) {
                $this->info("✓ Workspace {$workspace->name} (ID: {$workspace->id})");
                $success++;
            } else {
                $this->error("✗ Workspace {$workspace->name} (ID: {$workspace->id}) — HTTP {$response->status()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Success: {$success}, Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
