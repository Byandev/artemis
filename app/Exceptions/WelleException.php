<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Welle answered, but not usefully — an unexpected status, or a body the client
 * could not make sense of. Worth retrying: the queue will.
 */
class WelleException extends RuntimeException
{
    public static function fromStatus(string $action, int $status, string $body = ''): self
    {
        $detail = $body === '' ? '' : ' '.mb_substr($body, 0, 200);

        return new self("Welle {$action} failed with HTTP {$status}.{$detail}");
    }
}
