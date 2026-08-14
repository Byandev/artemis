<?php

namespace Modules\GencysERP\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin client over the n8n public API (the one behind Settings → n8n API).
 *
 * Only the execution-retry endpoint is wrapped, which is the one thing Artemis
 * needs that a webhook can't do: replay an execution that already ran, in
 * place, rather than starting a fresh scrape.
 *
 * Auth is the `X-N8N-API-KEY` header. Every method degrades to `false` rather
 * than throwing — a retry that can't reach n8n should surface as "couldn't
 * retry", not as a 500 on the Sync Health page.
 */
class N8nApi
{
    public function isConfigured(): bool
    {
        return filled(config('services.n8n.base_url'))
            && filled(config('services.n8n.api_key'));
    }

    /**
     * Replay an execution in n8n.
     *
     * $loadWorkflow controls which workflow version runs: true uses the current
     * saved version (so a fix to the flow is picked up by the retry), false
     * replays the version that ran originally. Defaulting to true is what makes
     * "fix the flow, hit retry" work.
     */
    public function retryExecution(int $executionId, bool $loadWorkflow = true): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $url = rtrim((string) config('services.n8n.base_url'), '/')."/api/v1/executions/{$executionId}/retry";

        try {
            $response = Http::withHeaders(['X-N8N-API-KEY' => (string) config('services.n8n.api_key')])
                ->timeout((int) config('services.n8n.api_timeout', 15))
                ->post($url, ['loadWorkflow' => $loadWorkflow]);
        } catch (Throwable $e) {
            Log::warning('n8n execution retry unreachable', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('n8n execution retry failed', [
                'execution_id' => $executionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
