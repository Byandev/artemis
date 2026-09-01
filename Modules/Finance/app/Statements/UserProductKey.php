<?php

namespace Modules\Finance\Statements;

/**
 * The composite key of a user-and-product row: "12|3" for user 12 on product 3,
 * with either side empty when it resolves to nobody / no product ("|3", "12|",
 * "|").
 *
 * A string rather than a pair so the totals keep the plain `array<string, …>`
 * shape the single-axis readings already use, and so the two halves can only be
 * joined and split one way.
 */
final class UserProductKey
{
    private const SEPARATOR = '|';

    /** Build a key from either half; null / '' means unresolved on that side. */
    public static function of(mixed $userId, mixed $productId): string
    {
        return self::part($userId).self::SEPARATOR.self::part($productId);
    }

    /** @return array{0:?int, 1:?int} [userId, productId], null where unresolved. */
    public static function split(string $key): array
    {
        [$user, $product] = array_pad(explode(self::SEPARATOR, $key, 2), 2, '');

        return [
            $user === '' ? null : (int) $user,
            $product === '' ? null : (int) $product,
        ];
    }

    /** The product half on its own, as the product-keyed readings write it. */
    public static function productOf(string $key): string
    {
        return (string) self::split($key)[1];
    }

    private static function part(mixed $id): string
    {
        return ($id === null || $id === '') ? '' : (string) (int) $id;
    }
}
