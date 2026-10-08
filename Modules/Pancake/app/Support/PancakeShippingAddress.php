<?php

namespace Modules\Pancake\Support;

/**
 * The `shipping_address` body for Pancake's order update: the location ids and
 * address line we fill in, on top of whatever the order already had.
 *
 * Name, phone and the like are carried over so the update never blanks them —
 * Pancake replaces the shipping address as a whole.
 */
class PancakeShippingAddress
{
    private const CARRIED_OVER = ['full_name', 'phone_number', 'country_code', 'post_code'];

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
