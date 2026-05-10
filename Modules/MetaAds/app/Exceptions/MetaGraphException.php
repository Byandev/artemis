<?php

namespace Modules\MetaAds\Exceptions;

use RuntimeException;
use Throwable;

class MetaGraphException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?int $errorSubcode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $fbtraceId = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponseBody(array $body, int $httpStatus): self
    {
        $error = $body['error'] ?? [];

        return new self(
            message: $error['message'] ?? 'Unknown Meta Graph error',
            errorCode: isset($error['code']) ? (int) $error['code'] : null,
            errorSubcode: isset($error['error_subcode']) ? (int) $error['error_subcode'] : null,
            errorType: $error['type'] ?? null,
            fbtraceId: $error['fbtrace_id'] ?? null,
            httpStatus: $httpStatus,
        );
    }

    public function isRateLimited(): bool
    {
        // Meta uses code 17, 4, 32, 613 plus subcodes 2446079 etc. for throttling.
        return in_array($this->errorCode, [4, 17, 32, 613], true);
    }

    public function isTokenInvalid(): bool
    {
        // OAuthException with code 190 = invalid/expired token.
        return $this->errorCode === 190;
    }
}
