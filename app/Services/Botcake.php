<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Botcake
{
    public function __construct(public string $pageId, public string $token) {}

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function updateCustomField($psid, $customFieldId, $value)
    {
        $response = Http::withHeader('access-token', $this->token)
            ->post("https://botcake.io/api/public_api/v1/pages/{$this->pageId}/customer/$psid/customer_fields", [
                'data' => [
                    [
                        'id' => $customFieldId,
                        'value' => $value,
                    ],
                ],
            ]);

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
        $response = Http::withHeader('access-token', $this->token)
            ->post("https://botcake.io/api/public_api/v1/pages/$this->pageId/flows/send_flow", [
                'psid' => $psid,
                'flow_id' => $flow_id,
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to send flow: '.$response->getStatusCode());
        }

        $response = $response->json();

        if (! $response['success']) {
            throw new Exception('Failed to send flow: '.$response['message'] ?? '');
        }
    }
}
