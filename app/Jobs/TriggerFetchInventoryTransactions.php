<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerFetchInventoryTransactions implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $webhookUrl,
        public array $data,
    ) {
        $this->onQueue('inventory');
    }

    public function handle(): void
    {
        $response = Http::timeout(30)->post($this->webhookUrl, $this->data);

        if (! $response->successful()) {
            Log::warning('n8n webhook call failed for inventory transactions', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}