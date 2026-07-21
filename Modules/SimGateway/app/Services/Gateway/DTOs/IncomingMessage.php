<?php

namespace Modules\SimGateway\Services\Gateway\DTOs;

use Illuminate\Support\Carbon;

class IncomingMessage
{
    public function __construct(
        public string $fromNumber,
        public string $toNumber,
        public string $message,
        public Carbon $receivedAt,
        public ?string $providerMessageId = null,
    ) {}
}
