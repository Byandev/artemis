<?php

namespace Modules\SimGateway\Services\Gateway\DTOs;

class SimStatus
{
    public function __construct(
        public bool $online,
        public ?int $signalStrength = null,
        public ?string $balance = null,
        public ?string $carrier = null,
    ) {}
}
