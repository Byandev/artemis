<?php

namespace Modules\Products\Exceptions;

use Exception;

/**
 * The name generator could not produce a usable answer.
 *
 * The message on this exception is written to be shown to whoever pressed the
 * button, so it never carries the provider's own error text: an upstream error
 * body can quote the prompt back, and the prompt carries the workspace's brief.
 * The detail goes to the log instead.
 */
class RdpSuggestionFailed extends Exception
{
    public static function upstream(int $status): self
    {
        return new self(
            $status === 429
                ? 'The name generator is busy right now. Try again in a moment.'
                : 'The name generator could not be reached. Try again in a moment.'
        );
    }

    public static function unreachable(): self
    {
        return new self('The name generator could not be reached. Try again in a moment.');
    }

    public static function unusableAnswer(): self
    {
        return new self('The name generator returned something unusable. Try again.');
    }
}
