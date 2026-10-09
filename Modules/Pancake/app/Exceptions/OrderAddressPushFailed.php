<?php

namespace Modules\Pancake\Exceptions;

use Exception;

/**
 * Writing an address back to an order in Pancake did not work. The message is
 * written for whoever pressed the button; Pancake's own reply goes to the log.
 */
class OrderAddressPushFailed extends Exception
{
    public static function noPosToken(): self
    {
        return new self("This order's shop has no POS token. Add it on the Shops screen first.");
    }

    public static function refused(): self
    {
        return new self('Pancake did not accept the update. Try again, or update the order in Pancake.');
    }

    public static function unreachable(): self
    {
        return new self('Could not reach Pancake. Try again in a moment.');
    }
}
