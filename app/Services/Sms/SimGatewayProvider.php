<?php

namespace App\Services\Sms;

use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Services\Gateway\GatewayInterface;
use Throwable;

/**
 * Sends parcel-journey SMS through the in-house Artemis SIM Gateway module,
 * from a specific workspace SIM (chosen per page). Delivery is reported by the
 * device's push callbacks rather than polled here, so a successful hand-off is
 * treated as sent (`tracksDelivery: false`). The gateway also records the send
 * in its own Outbox.
 */
class SimGatewayProvider implements SmsProvider
{
    public function __construct(private ?int $simId) {}

    public function send(string $to, string $message): SmsSendResult
    {
        if (! $this->simId) {
            return SmsSendResult::failed('No SIM selected for the Artemis SIM Gateway.');
        }

        $sim = Sim::find($this->simId);
        if (! $sim) {
            return SmsSendResult::failed("SIM #{$this->simId} was not found.");
        }

        try {
            $response = app(GatewayInterface::class)->sendSms($sim, $to, $message);
        } catch (Throwable $e) {
            return SmsSendResult::failed('SIM Gateway error: '.$e->getMessage());
        }

        return $response->success
            ? SmsSendResult::accepted($response->providerMessageId, tracksDelivery: false)
            : SmsSendResult::failed($response->errorMessage ?? 'SIM Gateway rejected the message.');
    }
}
