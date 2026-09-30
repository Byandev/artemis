<?php

namespace Modules\Pancake\Support;

/**
 * Normalises the money in a pancake order payload back to its base currency.
 *
 * Pancake sometimes reports amounts in a scaled currency, where the trailing
 * number on the currency code is the scale factor: "PHP" means the amounts are
 * as written, "PHP100" means they have all been multiplied by 100. Every
 * monetary field in the payload is scaled the same way — the order's totals and
 * the prices on its line items alike — so both read through here.
 */
final class OrderCurrency
{
    /**
     * What the payload's amounts must be divided by: "PHP" => 1, "PHP100" => 100.
     *
     * Falls back to 1 for an unknown or missing code — leaving an amount as
     * written is recoverable, dividing it by a guess is not.
     */
    public static function divisor(?string $currency): int
    {
        if ($currency === null) {
            return 1;
        }

        preg_match('/(\d+)$/', $currency, $matches);

        return isset($matches[1]) ? max((int) $matches[1], 1) : 1;
    }

    /**
     * One payload amount in base currency, or null when the payload carries no
     * usable figure — a missing key, an empty string, or something non-numeric.
     *
     * Null rather than 0.0 so a caller can tell "pancake sent no price" from
     * "pancake sent a price of zero" and leave what it already has alone.
     */
    public static function amount(mixed $value, ?string $currency): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value / self::divisor($currency);
    }
}
