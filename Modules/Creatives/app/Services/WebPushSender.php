<?php

namespace Modules\Creatives\Services;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Modules\Creatives\Models\WebPushSubscription;

/**
 * Sends Web Push notifications to the Creatives Tracker web app (PWA). The
 * service worker (push-sw.js in the app) turns each payload into a
 * notification. Does nothing until the VAPID keys are configured.
 */
class WebPushSender
{
    /**
     * @param  array<int>  $userIds
     * @param  array{title: string, body: string, data: array}  $notification
     */
    public function sendToUsers(array $userIds, array $notification, bool $urgent = false): void
    {
        if (! $this->configured()) {
            return;
        }

        $subscriptions = WebPushSubscription::whereIn('user_id', $userIds)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $client = $this->client();
        $payload = json_encode([...$notification, 'url' => '/']);
        $options = [
            // A review reminder is stale after a day; don't deliver it later than that.
            'TTL' => 86400,
            // High urgency is what gets it through on a phone in battery saver.
            'urgency' => $urgent ? 'high' : 'normal',
        ];

        foreach ($subscriptions as $subscription) {
            $client->queueNotification(new Subscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding,
            ), $payload, $options);
        }

        $expired = [];

        foreach ($client->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            // 404 / 410: the browser dropped the subscription (site data
            // cleared, permission revoked, PWA removed). It will never work again.
            if ($report->isSubscriptionExpired()) {
                $expired[] = WebPushSubscription::hashEndpoint($report->getEndpoint());
            } else {
                Log::warning('Web push failed', ['endpoint' => $report->getEndpoint(), 'reason' => $report->getReason()]);
            }
        }

        if ($expired) {
            WebPushSubscription::whereIn('endpoint_hash', $expired)->delete();
        }
    }

    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    protected function client(): WebPush
    {
        return new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);
    }
}
