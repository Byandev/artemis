<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * InfoTxt (myinfotxt.com) SMS provider. Sends via a GET to `/send.php` with the
 * API key and user id in the query string. InfoTxt returns a `smsid` that is
 * later polled for delivery status (see CheckParcelUpdateNotification), so this
 * provider reports `tracksDelivery: true`.
 */
class InfoTxtProvider implements SmsProvider
{
    public function __construct(
        private string $apiKey,
        private string $userId,
        private string $baseUrl,
    ) {}

    public function send(string $to, string $message): SmsSendResult
    {
        $response = Http::get(rtrim($this->baseUrl, '/').'/send.php', [
            'SMS' => $message,
            'ApiKey' => $this->apiKey,
            'Mobile' => $to,
            'UserID' => $this->userId,
        ]);

        if (! $response->successful()) {
            return SmsSendResult::failed('Request failed');
        }

        $body = $response->json();

        if (isset($body['status']) && $body['status'] === '00') {
            return SmsSendResult::accepted($body['smsid'] ?? null, tracksDelivery: true);
        }

        return SmsSendResult::rejected(json_encode($body));
    }
}
