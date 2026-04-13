<?php

namespace Modules\Pancake\Support;

class JourneyUpdateNormalizer
{
    /**
     * Normalize a single raw partner update entry.
     *
     * Fixes two known API quirks:
     *   1. Some entries use 'update_at' instead of 'updated_at'.
     *   2. When 'note' is empty the human-readable description lives in 'status',
     *      so we map it to a canonical status name and move it to 'note'.
     */
    public function normalize(array $item): array
    {
        $item = $this->fixTimestampKey($item);
        $item = $this->resolveStatus($item);
        $item = $this->resolveRiderInfo($item);

        return $item;
    }

    private function fixTimestampKey(array $item): array
    {
        if (! isset($item['updated_at'])) {
            $item['updated_at'] = $item['update_at'];
        }

        return $item;
    }

    /**
     * For 'On Delivery' entries, parse rider name and mobile from the 【...】 bracket notation.
     * Expected format: 【...】【Name : +63XXXXXXXXX】
     * Adds 'rider_name' and 'rider_mobile' keys (null if not parseable).
     */
    private function resolveRiderInfo(array $item): array
    {
        $item['rider_name'] = null;
        $item['rider_mobile'] = null;

        if ($item['status'] !== 'On Delivery') {
            return $item;
        }

        if (! preg_match_all('/【(.*?)】/', $item['note'], $matches) || ! isset($matches[1][1])) {
            return $item;
        }

        [$name, $rawMobile] = array_map('trim', explode(':', $matches[1][1], 2));

        $item['rider_name'] = $name;
        $item['rider_mobile'] = '0'.substr(preg_replace('/\D/', '', $rawMobile), -10);

        return $item;
    }

    private function resolveStatus(array $item): array
    {
        if ($item['note'] !== '') {
            return $item;
        }

        $raw = $item['status'];

        $item['status'] = match (true) {
            str_contains($raw, 'is sending') => 'On Delivery',
            str_contains($raw, 'send package') => 'Arrival',
            str_contains($raw, 'Delivery Failed') => 'Problematic',
            str_contains($raw, 'Return Register') => 'Return Register',
            str_contains($raw, 'has been pick') => 'Picked Up',
            str_contains($raw, 'delivered successfully') => 'Delivered',
            str_contains($raw, 'Return successfully') => 'Return Delivered',
            default => $raw,
        };

        $item['note'] = $raw;

        return $item;
    }
}
