<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * SendGate (sendgate.ph) SMS provider. Sends via a JSON POST to
 * `/api/v1/messages` authenticated with a Bearer API key, choosing which SIM to
 * send from via `sim_id`.
 *
 * We do not poll SendGate for delivery status, so a successful (2xx) send is
 * treated as sent immediately (`tracksDelivery: false`). The returned message id
 * is stored for reference only.
 */
class SendGateProvider implements SmsProvider
{
    public function __construct(
        private string $apiKey,
        private string $simId,
        private string $baseUrl,
    ) {}

    public function send(string $to, string $message): SmsSendResult
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/api/v1/messages', [
                'sim_id' => $this->simId,
                'to' => $to,
                'message' => $message,
            ]);

        if (! $response->successful()) {
            return SmsSendResult::failed(json_encode($response->json() ?: ['status' => $response->status()]));
        }

        $body = $response->json();
        $messageId = $body['id'] ?? $body['data']['id'] ?? null;

        return SmsSendResult::accepted(
            $messageId !== null ? (string) $messageId : null,
            tracksDelivery: false,
        );
    }
}
