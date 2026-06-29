<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysSyncRun;
use Throwable;

/**
 * Fires the n8n webhook that logs into Gencys ERP once and fetches the
 * transaction history for a chunk of inventory items, reusing that session.
 * The payload carries the workspace's ERP credentials, the callback URL and
 * the items ({ id, keyword }) n8n loops over.
 *
 * $syncRunIds are the pending GencysSyncRun rows the trigger command opened for
 * this chunk. If the handshake to n8n fails the items never get a callback, so
 * we fail those runs here; otherwise the callback resolves them.
 */
class FetchInventoryItemTransactionHistory implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $webhookUrl,
        public array $data,
        public array $syncRunIds = [],
    ) {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(30)->post($this->webhookUrl, $this->data);
        } catch (Throwable $e) {
            Log::warning('n8n webhook unreachable for transaction history record', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->failPendingRuns('n8n webhook unreachable: '.$e->getMessage());

            throw $e;
        }

        if (! $response->successful()) {
            Log::warning('n8n webhook call failed for transaction history record', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->failPendingRuns("n8n webhook returned HTTP {$response->status()}");
        }
    }

    /** Fail this chunk's pending runs when the outbound call never reaches n8n. */
    private function failPendingRuns(string $message): void
    {
        if (empty($this->syncRunIds)) {
            return;
        }

        GencysSyncRun::query()
            ->whereIn('id', $this->syncRunIds)
            ->pending()
            ->get()
            ->each
            ->fail($message);
    }
}
