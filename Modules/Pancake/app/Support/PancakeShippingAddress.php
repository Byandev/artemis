<?php

namespace Modules\Pancake\Support;

/**
 * The `shipping_address` body for Pancake's order update: the location ids and
 * the address line we fill in, plus the order's own country / post code.
 *
 * The customer's name and phone are never sent — the auto-fill only owns the
 * address, and Pancake keeps the ones the order already has.
 */
class PancakeShippingAddress
{
    private const CARRIED_OVER = ['country_code', 'post_code'];

    public static function build(array $existing, string $address, string $provinceId, string $districtId, string $communeId): array
    {
        return array_filter([
            ...array_intersect_key($existing, array_flip(self::CARRIED_OVER)),
            'address' => $address,
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'commune_id' => $communeId,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
