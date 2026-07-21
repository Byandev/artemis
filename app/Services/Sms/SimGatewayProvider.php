<?php

namespace App\Services\Sms;

use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Services\Gateway\GatewayInterface;
use Throwable;

/**
 * Sends parcel-journey SMS through the in-house Artemis SIM Gateway module,
 * from a specific workspace SIM (chosen per page). The device reports the final
 * status asynchronously by pushing a delivery report to our callback, so a
 * successful hand-off leaves the notification pending (`awaitsCallback`) until
 * that report lands. The gateway also records the send in its own Outbox.
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
            ? SmsSendResult::acceptedAwaitingCallback($response->providerMessageId)
            : SmsSendResult::failed($response->errorMessage ?? 'SIM Gateway rejected the message.');
    }
}
