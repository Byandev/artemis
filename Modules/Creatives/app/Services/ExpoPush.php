<?php

namespace Modules\Creatives\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Creatives\Models\PushToken;

/**
 * Sends pushes to the Creatives Tracker app through Expo's push service.
 * https://docs.expo.dev/push-notifications/sending-notifications/
 */
class ExpoPush
{
    private const URL = 'https://exp.host/--/api/v2/push/send';

    /** Expo accepts at most 100 messages per request. */
    private const CHUNK = 100;

    /**
     * Sends the same message to every token of each user. `$message` is the
     * Expo message without `to` (title, body, data, ...).
     *
     * @param  array<int>  $userIds
     */
    public function sendToUsers(array $userIds, array $message): void
    {
        $tokens = PushToken::whereIn('user_id', $userIds)->pluck('token')->all();

        $this->send(array_map(fn ($token) => ['to' => $token, ...$message], $tokens));
    }

    /** @param  array<int, array>  $messages  one message per token */
    public function send(array $messages): void
    {
        foreach (array_chunk($messages, self::CHUNK) as $chunk) {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->when(config('services.expo.access_token'), fn ($http, $token) => $http->withToken($token))
                ->post(self::URL, $chunk);

            if ($response->failed()) {
                Log::warning('Expo push request failed', ['status' => $response->status(), 'body' => $response->body()]);

                continue;
            }

            $this->pruneDeadTokens($chunk, $response->json('data') ?? []);
        }
    }

    /**
     * Tickets come back in the same order as the messages. A token Expo says
     * is no longer registered (app uninstalled, notifications revoked) will
     * never work again, so drop it.
     */
    private function pruneDeadTokens(array $messages, array $tickets): void
    {
        $dead = [];

        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$i]['to'])) {
                $dead[] = $messages[$i]['to'];
            } else {
                Log::warning('Expo push ticket error', ['ticket' => $ticket]);
            }
        }

        if ($dead) {
            PushToken::whereIn('token', $dead)->delete();
        }
    }
}
