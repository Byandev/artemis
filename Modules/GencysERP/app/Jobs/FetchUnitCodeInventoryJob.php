<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires the n8n webhook that logs into Gencys ERP and fetches the inventory
 * items for a single unit code. The payload carries the unit code's id (echoed
 * back on the callback) plus the ERP credentials and callback URL.
 */
class FetchUnitCodeInventoryJob implements ShouldQueue
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
            Log::warning('n8n webhook call failed for Gencys unit code inventories', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'row_id' => $this->data['row_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
