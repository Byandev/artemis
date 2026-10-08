<?php

namespace Modules\Pancake\Exceptions;

use Exception;

/**
 * Reading an order's address out of its conversation did not work.
 *
 * The message is written for whoever pressed the button. Provider and Pancake
 * error bodies stay in the log, since they can echo the customer's messages.
 */
class AddressExtractionFailed extends Exception
{
    public static function noConversation(): self
    {
        return new self('This order has no Messenger conversation to read.');
    }

    public static function noPageToken(): self
    {
        return new self("This order's page has no Pancake token. Add it on the Pages screen first.");
    }

    public static function conversationUnavailable(): self
    {
        return new self('Could not load the conversation from Pancake. Try again in a moment.');
    }

    public static function emptyConversation(): self
    {
        return new self('The conversation has no customer messages yet.');
    }

    public static function aiNotConfigured(): self
    {
        return new self('The address reader is not set up (no OpenRouter key).');
    }

    public static function aiUnavailable(?int $status = null): self
    {
        return new self(
            $status === 429
                ? 'The address reader is busy right now. Try again in a moment.'
                : 'The address reader could not be reached. Try again in a moment.'
        );
    }

    public static function aiUnusableAnswer(): self
    {
        return new self('The address reader returned something unusable. Try again.');
    }
}
