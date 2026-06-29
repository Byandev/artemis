<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires the n8n webhook that logs into Gencys ERP once and fetches the
 * transaction history for a chunk of inventory items, reusing that session.
 * The payload carries the workspace's ERP credentials, the callback URL and
 * the items ({ id, keyword }) n8n loops over.
 */
class FetchInventoryItemTransactionHistory implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $webhookUrl,
        public array $data,
    ) {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        $response = Http::timeout(30)->post($this->webhookUrl, $this->data);

        if (! $response->successful()) {
            Log::warning('n8n webhook call failed for transaction history record', [
                'inventory_item_id' => $this->data['inventory_item_id'] ?? null,
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
