<?php

namespace Modules\SimGateway\Services\Gateway;

use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Services\Gateway\DTOs\GatewayResponse;
use Modules\SimGateway\Services\Gateway\DTOs\IncomingMessage;
use Modules\SimGateway\Services\Gateway\DTOs\SimStatus;

interface GatewayInterface
{
    public function sendSms(Sim $sim, string $to, string $message): GatewayResponse;

    /**
     * Send a batch of SMS in a single gateway request.
     *
     * @param  list<array{from: string, to: string, message: string}>  $tasks
     */
    public function sendBulkSms(Sim $sim, array $tasks): GatewayResponse;

    public function getSimStatus(Sim $sim): SimStatus;

    /**
     * @return array<int, IncomingMessage>
     */
    public function pollIncomingMessages(Sim $sim): array;
}
