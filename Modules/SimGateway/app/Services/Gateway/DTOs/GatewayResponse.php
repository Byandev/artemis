<?php

namespace Modules\SimGateway\Services\Gateway\DTOs;

class GatewayResponse
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $status = null,
        public ?string $errorMessage = null,
        public int $segments = 1,
    ) {}
}
