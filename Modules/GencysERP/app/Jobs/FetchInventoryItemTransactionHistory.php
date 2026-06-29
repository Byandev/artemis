<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\ErpSyncChunk;
use Throwable;

/**
 * Fires the n8n webhook that logs into Gencys ERP once and fetches the
 * transaction history for a chunk of inventory items, reusing that session.
 * The payload carries the workspace's ERP credentials, the callback URL and
 * the items ({ id, keyword }) n8n loops over.
 *
 * When a sync chunk id is given, the job records the webhook outcome on it so
 * the monitoring page can show — and retry — failed dispatches.
 */
class FetchInventoryItemTransactionHistory implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $webhookUrl,
        public array $data,
        public ?int $syncChunkId = null,
    ) {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        $chunk = $this->syncChunkId ? ErpSyncChunk::find($this->syncChunkId) : null;
        $chunk?->markDispatched();

        try {
            $response = Http::timeout(30)->post($this->webhookUrl, $this->data);
        } catch (Throwable $e) {
            Log::warning('n8n webhook call threw for transaction history record', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $chunk?->markFailed(null, $e->getMessage());

            return;
        }

        if ($response->successful()) {
            $chunk?->markSent($response->status());

            return;
        }

        Log::warning('n8n webhook call failed for transaction history record', [
            'workspace_id' => $this->data['workspace_id'] ?? null,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        $chunk?->markFailed($response->status(), $response->body());
    }
}
