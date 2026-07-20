<?php

namespace Modules\SimGateway\Services\Gateway;

use Illuminate\Support\Str;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Jobs\SimulateMessageDeliveryJob;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;
use Modules\SimGateway\Services\Gateway\DTOs\GatewayResponse;
use Modules\SimGateway\Services\Gateway\DTOs\SimStatus;

/**
 * Stub gateway used for local development and tests. Creates a DB row and
 * dispatches a job that fakes carrier delivery latency.
 */
class StubGateway implements GatewayInterface
{
    public function sendSms(Sim $sim, string $to, string $message): GatewayResponse
    {
        $segments = (int) max(1, (int) ceil(mb_strlen($message) / 160));

        $sms = SmsMessage::create([
            'workspace_id' => $sim->workspace_id,
            'sim_id' => $sim->id,
            'direction' => MessageDirection::Outbound,
            'from_number' => $sim->phone_number,
            'to_number' => $to,
            'message' => $message,
            'segments' => $segments,
            'status' => MessageStatus::Queued,
            'provider_message_id' => 'stub_'.Str::random(16),
            // Left null while Queued — SimulateMessageDeliveryJob stamps sent_at
            // once the message is (fake-)dispatched to the carrier.
        ]);

        SimulateMessageDeliveryJob::dispatch($sms->id)->delay(now()->addSeconds(random_int(2, 5)));

        return new GatewayResponse(
            success: true,
            providerMessageId: $sms->provider_message_id,
            status: MessageStatus::Queued->value,
            segments: $segments,
        );
    }

    /**
     * Fake a batch send: one DB row + simulated-delivery job per task.
     *
     * @param  list<array{from: string, to: string, message: string}>  $tasks
     */
    public function sendBulkSms(Sim $sim, array $tasks): GatewayResponse
    {
        $totalSegments = 0;

        foreach ($tasks as $task) {
            $to = (string) ($task['to'] ?? '');
            $message = (string) ($task['message'] ?? '');
            $segments = (int) max(1, (int) ceil(mb_strlen($message) / 160));
            $totalSegments += $segments;

            $sms = SmsMessage::create([
                'workspace_id' => $sim->workspace_id,
                'sim_id' => $sim->id,
                'direction' => MessageDirection::Outbound,
                'from_number' => $sim->phone_number,
                'to_number' => $to,
                'message' => $message,
                'segments' => $segments,
                'status' => MessageStatus::Queued,
                'provider_message_id' => 'stub_'.Str::random(16),
            ]);

            SimulateMessageDeliveryJob::dispatch($sms->id)->delay(now()->addSeconds(random_int(2, 5)));
        }

        return new GatewayResponse(
            success: true,
            providerMessageId: null,
            status: MessageStatus::Queued->value,
            segments: $totalSegments,
        );
    }

    public function getSimStatus(Sim $sim): SimStatus
    {
        return new SimStatus(
            online: $sim->status->value === 'active',
            signalStrength: random_int(70, 99),
            balance: '₱'.number_format(random_int(50, 500), 2),
            carrier: $sim->carrier->value,
        );
    }

    public function pollIncomingMessages(Sim $sim): array
    {
        // Stub does not poll — incoming messages are seeded for demos.
        return [];
    }
}
