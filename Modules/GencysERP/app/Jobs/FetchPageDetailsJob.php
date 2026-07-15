<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysSyncRun;
use Throwable;

/**
 * Fires the n8n webhook that logs into Gencys ERP and scrapes the detail (POS
 * token, shop id, page/fb ids) for a single page, reusing that session. The
 * payload carries the workspace's ERP credentials, the callback URL and the
 * page_id n8n fetches.
 *
 * $syncRunIds are the pending GencysSyncRun rows the trigger opened for this
 * page; if the handshake to n8n fails we fail them here, otherwise the callback
 * resolves them.
 */
class FetchPageDetailsJob implements ShouldQueue
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
            Log::warning('n8n webhook unreachable for page details', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'page_id' => $this->data['page_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->failPendingRuns('n8n webhook unreachable: '.$e->getMessage());

            throw $e;
        }

        if (! $response->successful()) {
            Log::warning('n8n webhook call failed for page details', [
                'workspace_id' => $this->data['workspace_id'] ?? null,
                'page_id' => $this->data['page_id'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->failPendingRuns("n8n webhook returned HTTP {$response->status()}");
        }
    }

    /** Fail this page's pending runs when the outbound call never reaches n8n. */
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
