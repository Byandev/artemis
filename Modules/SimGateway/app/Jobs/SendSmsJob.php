<?php

namespace Modules\SimGateway\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Services\Gateway\GatewayInterface;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $simId,
        public string $toNumber,
        public string $message,
    ) {}

    public function handle(GatewayInterface $gateway): void
    {
        $sim = Sim::find($this->simId);
        if (! $sim) {
            return;
        }

        $gateway->sendSms($sim, $this->toNumber, $this->message);
    }
}
