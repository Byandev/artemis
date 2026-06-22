<?php

namespace App\Console\Commands;

use App\Jobs\TriggerFetchPurchaseOrderRecord;
use Illuminate\Console\Command;
use Modules\Inventory\Models\InventoryItem;

class TriggerFetchERPPurchaseOrders extends Command
{
    protected $signature = 'trigger-fetch-erp-purchase-orders {--delay=30} {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each inventory item to fetch its ERP purchase orders';

    public function handle()
    {
        $webhookUrl = config('services.n8n.purchase_order_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n purchase order webhook URL is not configured (services.n8n.purchase_order_webhook_url).');

            return 1;
        }

        $items = InventoryItem::whereHas('workspace', fn ($q) => $q->whereHas('apiKeys'))
            ->with('workspace.apiKeys')
            ->get();

        $total = $items->count();

        if ($total === 0) {
            $this->warn('No inventory items found for workspaces with an API key.');

            return 0;
        }

        $delay = (int) $this->option('delay');
        $sync = (bool) $this->option('sync');

        // URL n8n posts the synced ERP purchase orders back to. Configurable so it can
        // point at a reachable host (local n8n, staging, tunnel) instead of being hardcoded.
        // Defaults to APP_URL when N8N_INVENTORY_SYNC_CALLBACK_URL isn't set.
        //        $callbackBase = rtrim(config('services.n8n.inventory_sync_callback_url') ?: config('app.url'), '/');
        $callbackBase = 'https://stretchy-wanetta-unwinning.ngrok-free.dev';
        $callbackUrl = "{$callbackBase}/api/v1/public/purchase-orders/sync";

        $this->info($sync
            ? "Sending {$total} inventory item(s) synchronously (no queue)"
            : "Dispatching {$total} inventory item(s) with {$delay}s delay between jobs");

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
                'transaction_keywords' => $item->transaction_keywords,
                'webhook_url' => $callbackUrl,
            ];

            if ($sync) {
                TriggerFetchPurchaseOrderRecord::dispatchSync($webhookUrl, $data);
                $this->info("Sent for item {$item->id}");
            } else {
                $jobDelaySeconds = $dispatched * $delay;

                TriggerFetchPurchaseOrderRecord::dispatch($webhookUrl, $data)
                    ->delay(now()->addSeconds($jobDelaySeconds));

                $this->info("Dispatched for item {$item->id} (delay: {$jobDelaySeconds}s)");
            }

            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}");

        return 0;
    }
}
