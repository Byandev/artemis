<?php

namespace App\Services;

use App\Models\OutgoingApiLog;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Botcake
{
    public function __construct(public string $pageId, public string $token) {}

    /**
     * Turn a failed Botcake call into a message worth showing a user.
     *
     * Botcake answers "invalid_page_id" when the access token isn't authorized
     * for the page id it was sent with, which reads as a page problem but is
     * always a token mismatch — name the actual fix instead of the raw code.
     */
    public static function describeError(\Throwable $e): string
    {
        return str_contains($e->getMessage(), 'invalid_page_id')
            ? "Botcake rejected this page (invalid_page_id). The Botcake access token doesn't match this page's ID — re-copy the access token from Botcake for this exact page."
            : 'Botcake: '.$e->getMessage();
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function updateCustomField($psid, $customFieldId, $value)
    {
        $url = "https://botcake.io/api/public_api/v1/pages/{$this->pageId}/customer/$psid/customer_fields";
        $payload = ['data' => [['id' => $customFieldId, 'value' => $value]]];

        $startedAt = now();

        $response = Http::withHeader('access-token', $this->token)
            ->post($url, $payload);

        if (config('settings.parcel_journey_notification_logs_enabled')) {
            OutgoingApiLog::create([
                'service' => 'botcake',
                'action' => 'updateCustomField',
                'http_method' => 'POST',
                'url' => $url,
                'request_payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => $response->json() ?? ['raw' => $response->body()],
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'context' => ['page_id' => $this->pageId, 'psid' => $psid, 'custom_field_id' => $customFieldId],
            ]);
        }

        if ($response->failed()) {
            throw new Exception('Failed to send flow: '.$response->getStatusCode());
        }

        $response = $response->json();

        if (! $response['success']) {
            throw new Exception('Failed to send flow: '.$response['message'] ?? '');
        }
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function sendFlow($psid, $flow_id)
    {
        $url = "https://botcake.io/api/public_api/v1/pages/$this->pageId/flows/send_flow";
        $payload = ['psid' => $psid, 'flow_id' => $flow_id];

        $startedAt = now();

        $response = Http::withHeader('access-token', $this->token)
            ->post($url, $payload);

        if (config('settings.parcel_journey_notification_logs_enabled')) {
            OutgoingApiLog::create([
                'service' => 'botcake',
                'action' => 'sendFlow',
                'http_method' => 'POST',
                'url' => $url,
                'request_payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => $response->json() ?? ['raw' => $response->body()],
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'context' => ['page_id' => $this->pageId, 'psid' => $psid, 'flow_id' => $flow_id],
            ]);
        }

        if ($response->failed()) {
            throw new Exception('Failed to send flow: '.$response->getStatusCode());
        }

        $response = $response->json();

        if (! $response['success']) {
            throw new Exception('Failed to send flow: '.$response['message'] ?? '');
        }
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetchFlows(): array
    {
        $response = Http::withHeader('access-token', $this->token)
            ->get("https://botcake.io/api/public_api/v1/pages/$this->pageId/flows/");

        $body = $response->json();

        if ($response->failed() || ($body['success'] ?? true) === false) {
            throw new Exception($body['message'] ?? 'Failed to fetch flows: '.$response->status());
        }

        return $response->json('data.flows', []);
    }

    /**
     * Fetch the page's custom field definitions from Botcake.
     *
     * On failure the thrown message carries Botcake's own reason (e.g.
     * "invalid_page_id") when present, so callers can surface it verbatim.
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetchCustomFields(): array
    {
        $url = "https://botcake.io/api/public_api/v1/pages/$this->pageId/custom_fields";

        // The endpoint is paginated (?page=0,1,2,…). Walk pages until one comes
        // back empty (or stops adding new fields) so we return the full list.
        $all = [];
        $seen = [];

        for ($page = 0; $page < 100; $page++) {
            $response = Http::withHeader('access-token', $this->token)
                ->get($url, ['page' => $page]);

            $body = $response->json();

            if ($response->failed() || ($body['success'] ?? true) === false) {
                throw new Exception($body['message'] ?? 'Failed to fetch custom fields: '.$response->status());
            }

            $data = $response->json('data', []);

            if (empty($data)) {
                break;
            }

            $added = 0;
            foreach ($data as $field) {
                $id = $field['id'] ?? null;

                if ($id === null) {
                    $all[] = $field;
                    $added++;
                } elseif (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $all[] = $field;
                    $added++;
                }
            }

            // Guard against endpoints that ignore ?page and keep returning the
            // same records: stop once a page contributes nothing new.
            if ($added === 0) {
                break;
            }
        }

        return $all;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetchSequences(): array
    {
        $response = Http::withHeader('access-token', $this->token)
            ->get("https://botcake.io/api/public_api/v1/pages/$this->pageId/sequences/");

        if ($response->failed()) {
            throw new Exception('Failed to fetch sequences: '.$response->status());
        }

        return $response->json('data', []);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetchFlowStatistics(string $flowId): array
    {
        $response = Http::withHeader('access-token', $this->token)
            ->get("https://botcake.io/api/public_api/v1/pages/$this->pageId/flows/$flowId/statistics");

        if (! $response->ok()) {
            throw new Exception('Failed to fetch flow statistics: '.$response->status().' '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetchSequenceStatistics(string $sequenceId): array
    {
        $response = Http::withHeader('access-token', $this->token)
            ->get("https://botcake.io/api/public_api/v1/pages/$this->pageId/sequences/$sequenceId/statistics");

        if (! $response->ok()) {
            throw new Exception('Failed to fetch sequence statistics: '.$response->status().' '.$response->body());
        }

        return $response->json('data', []);
    }
}
