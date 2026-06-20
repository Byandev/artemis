<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires the n8n webhook that logs into Gencys ERP and fetches a workspace's
 * daily sales tracker for a given date. The whole payload (including the ERP
 * credentials and the callback URL n8n posts results back to) is forwarded as-is.
 */
class FetchDailySalesTrackerJob implements ShouldQueue
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
            Log::warning('n8n webhook call failed for Gencys daily sales tracker', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'date' => $this->data['date'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
