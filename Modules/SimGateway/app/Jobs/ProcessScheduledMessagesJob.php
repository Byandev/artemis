<?php

namespace Modules\SimGateway\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\SimGateway\Enums\ScheduledMessageStatus;
use Modules\SimGateway\Models\ScheduledMessage;
use Modules\SimGateway\Services\Gateway\GatewayInterface;

class ProcessScheduledMessagesJob implements ShouldQueue
{
    use Queueable;

    public function handle(GatewayInterface $gateway): void
    {
        ScheduledMessage::query()
            ->where('status', ScheduledMessageStatus::Pending)
            ->where('scheduled_at', '<=', now())
            ->get()
            ->each(function (ScheduledMessage $message) use ($gateway) {
                $sim = $message->sim;
                if (! $sim) {
                    $message->update(['status' => ScheduledMessageStatus::Failed]);

                    return;
                }

                $gateway->sendSms($sim, $message->to_number, $message->message);
                $message->update([
                    'status' => ScheduledMessageStatus::Sent,
                    'sent_at' => now(),
                ]);
            });
    }
}
