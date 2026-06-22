<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerFetchPurchaseOrderRecord implements ShouldQueue
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
            Log::warning('n8n webhook call failed for purchase order record', [
                'inventory_item_id' => $this->data['inventory_item_id'] ?? null,
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
