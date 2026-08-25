<?php

namespace Modules\GencysERP\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads execution state back out of n8n.
 *
 * We know which execution took each sync run (SendGencysSyncGroup records the id
 * n8n answers with), but not how it went — a run stuck `pending` looks identical
 * whether n8n crashed, is still grinding through the ERP, or finished and failed
 * to call us back. This asks.
 *
 * Read-only by design. n8n's public API exposes executions for listing, fetching
 * and deleting; there is no retry on it — retrying an execution is an editor
 * action behind a browser session. Re-running a sync run therefore means sending
 * the work again, which BatchRunner does, not poking n8n.
 */
class N8nApi
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function make(): self
    {
        return new self(
            config('services.n8n.api_url'),
            config('services.n8n.api_key'),
        );
    }

    /** Whether the instance is wired up at all. */
    public function isConfigured(): bool
    {
        return filled($this->baseUrl) && filled($this->apiKey);
    }

    /**
     * Look one execution up.
     *
     * Returns a state rather than just null, because the ways this can come back
     * empty are not interchangeable: a rejected API key is a configuration
     * problem someone must fix, a 404 usually means n8n pruned the execution, and
     * an unreachable host is neither. Reporting all three as "not found" sends
     * people looking in the wrong place.
     *
     * @return array{state: string, message: ?string, execution: ?array<string, mixed>}
     */
    public function lookup(string $executionId): array
    {
        if (! $this->isConfigured()) {
            return $this->result('unconfigured', 'n8n API is not configured (set N8N_API_URL and N8N_API_KEY).');
        }

        if ($executionId === '') {
            return $this->result('unknown', 'No n8n execution was recorded for this run.');
        }

        try {
            $response = Http::withHeaders(['X-N8N-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->timeout(10)
                ->get($this->url("/api/v1/executions/{$executionId}"), ['includeData' => 'false']);
        } catch (Throwable $e) {
            Log::warning('n8n API unreachable while looking up an execution', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);

            return $this->result('unreachable', 'Could not reach n8n.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('n8n rejected the API key', ['status' => $response->status()]);

            return $this->result(
                'unauthorized',
                'n8n rejected the API key — generate a new one in n8n under Settings → n8n API.',
            );
        }

        if ($response->status() === 404) {
            // n8n only keeps what its save settings say to keep. Instances are
            // commonly configured to discard successful runs
            // (EXECUTIONS_DATA_SAVE_ON_SUCCESS=none), so a 404 here is usually
            // the execution having gone well, not anything being wrong.
            return $this->result(
                'not_found',
                "n8n kept no record of execution {$executionId} — it discards executions its save settings don't retain (often the successful ones).",
            );
        }

        if (! $response->successful()) {
            return $this->result('unreachable', "n8n answered HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            return $this->result('unreachable', 'n8n returned something unreadable.');
        }

        $workflowId = $this->stringOrNull($body['workflowId'] ?? null);

        return $this->result('found', null, [
            'id' => (string) ($body['id'] ?? $executionId),
            'status' => $this->status($body),
            'finished' => (bool) ($body['finished'] ?? false),
            'started_at' => $this->stringOrNull($body['startedAt'] ?? null),
            'stopped_at' => $this->stringOrNull($body['stoppedAt'] ?? null),
            'workflow_id' => $workflowId,
            'mode' => $this->stringOrNull($body['mode'] ?? null),
            'error' => $this->error($body),
            'url' => $this->executionUrl($executionId, $workflowId),
        ]);
    }

    /** The execution itself, or null for any of the ways it can be missing. */
    public function execution(string $executionId): ?array
    {
        return $this->lookup($executionId)['execution'];
    }

    /** @return array{state: string, message: ?string, execution: ?array<string, mixed>} */
    private function result(string $state, ?string $message = null, ?array $execution = null): array
    {
        return ['state' => $state, 'message' => $message, 'execution' => $execution];
    }

    /**
     * A link straight to the execution in the n8n editor.
     *
     * Only built when the workflow is known — n8n's execution view is nested
     * under its workflow, and a guessed URL that 404s is worse than none.
     */
    public function executionUrl(string $executionId, ?string $workflowId): ?string
    {
        if (! filled($this->baseUrl) || ! filled($workflowId)) {
            return null;
        }

        return $this->url("/workflow/{$workflowId}/executions/{$executionId}");
    }

    /**
     * n8n reports `status` directly on newer versions; older ones only say
     * whether it finished, so derive something equivalent.
     */
    private function status(array $body): string
    {
        if (filled($body['status'] ?? null)) {
            return (string) $body['status'];
        }

        if (filled($body['waitTill'] ?? null)) {
            return 'waiting';
        }

        if ($body['finished'] ?? false) {
            return 'success';
        }

        // Stopped without finishing is a failure; still going is just running.
        return filled($body['stoppedAt'] ?? null) ? 'error' : 'running';
    }

    /** The failure message n8n recorded, when it recorded one. */
    private function error(array $body): ?string
    {
        $message = data_get($body, 'data.resultData.error.message')
            ?? data_get($body, 'data.resultData.error.description');

        return $this->stringOrNull($message);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function stringOrNull(mixed $value): ?string
    {
        return (is_string($value) || is_numeric($value)) && $value !== '' ? (string) $value : null;
    }
}
