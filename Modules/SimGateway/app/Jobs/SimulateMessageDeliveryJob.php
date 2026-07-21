<?php

namespace Modules\SimGateway\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Models\SmsMessage;

/**
 * Stub-only: fakes carrier-side delivery so dashboards look alive in dev.
 */
class SimulateMessageDeliveryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $smsMessageId) {}

    public function handle(): void
    {
        $sms = SmsMessage::find($this->smsMessageId);
        if (! $sms) {
            return;
        }

        // Either outcome means the message left the SIM for the carrier, so we
        // stamp sent_at now (if it wasn't already) — this is the moment the
        // message was actually sent, as opposed to when it was queued.
        $sentAt = $sms->sent_at ?? now();

        if (random_int(1, 100) > 5) {
            $sms->update([
                'status' => MessageStatus::Delivered,
                'sent_at' => $sentAt,
                'delivered_at' => now(),
            ]);
        } else {
            $sms->update([
                'status' => MessageStatus::Failed,
                'sent_at' => $sentAt,
                'error_message' => 'Simulated carrier-side delivery failure.',
            ]);
        }
    }
}
