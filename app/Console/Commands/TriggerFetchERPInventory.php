<?php

namespace App\Console\Commands;

use App\Jobs\TriggerFetchInventoryKeywordRecord;
use Illuminate\Console\Command;
use Modules\Inventory\Models\InventoryItem;

class TriggerFetchERPInventory extends Command
{
    protected $signature = 'trigger-fetch-erp-inventory {--delay=30}';

    protected $description = 'Trigger n8n webhook for each inventory item with sales keywords to fetch ERP data';

    public function handle()
    {
        $webhookUrl = config('services.n8n.inventory_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n inventory webhook URL is not configured (services.n8n.inventory_webhook_url).');

            return 1;
        }

        $items = InventoryItem::whereNotNull('sales_keywords')
            ->where('sales_keywords', '!=', '')
            ->whereHas('workspace', fn ($q) => $q->whereHas('apiKeys'))
            ->with('workspace.apiKeys')
            ->get();

        $total = $items->count();

        if ($total === 0) {
            $this->warn('No inventory items found with sales keywords.');

            return 0;
        }

        $delay = (int) $this->option('delay');

        $this->info("Dispatching {$total} inventory item(s) with {$delay}s delay between jobs");

        $dispatched = 0;

        foreach ($items as $item) {
            $apiKey = $item->workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping item {$item->id} — no API key found for workspace {$item->workspace_id}.");

                continue;
            }

            $data = [
                'workspace_id' => $item->workspace_id,
                'workspace_api_key' => $apiKey->reveal(),
                'inventory_item_id' => $item->id,
                'sales_keywords' => $item->sales_keywords,
                'transaction_keywords' => $item->transaction_keywords,
                'webhook_url' => config('app.url').'/api/v1/public/inventory-items/sync',
            ];

            $jobDelaySeconds = $dispatched * $delay;

            TriggerFetchInventoryKeywordRecord::dispatch($webhookUrl, $data)
                ->delay(now()->addSeconds($jobDelaySeconds));

            $this->info("Dispatched for item {$item->id} — SKU: {$item->sku} (delay: {$jobDelaySeconds}s)");
            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}");

        return 0;
    }
}
