<?php

namespace Modules\Pancake\Support;

/**
 * Narrows a conversation down to the messages that could hold the delivery
 * address, so the AI reads a handful of lines instead of the whole chat.
 *
 * A message counts when it has an address word ("brgy", "purok", "near"…) or
 * names a province or city from Pancake's list. Each one the customer wrote
 * keeps the message before it, which is usually the page asking for the
 * address. When nothing the customer wrote counts, the result is empty: the
 * auto-fill treats that as "no address yet" without paying for an AI call.
 */
class AddressMessageFilter
{
    /** The most messages handed to the AI after filtering. */
    public const MAX_SELECTED = 12;

    private const ADDRESS_WORDS = [
        'brgy', 'barangay', 'bgy', 'brg', 'purok', 'prk', 'sitio', 'zone', 'blk', 'block', 'lot', 'phase',
        'subd', 'subdivision', 'village', 'vill', 'street', 'st', 'road', 'rd', 'avenue', 'ave', 'highway',
        'hwy', 'city', 'municipality', 'mun', 'province', 'prov', 'poblacion', 'pob', 'near', 'beside',
        'tapat', 'likod', 'harap', 'katabi', 'malapit', 'landmark', 'address', 'addr', 'zip', 'zipcode',
        'bldg', 'building', 'unit', 'tower', 'compound', 'cmpd', 'apartment', 'apt',
    ];

    public function __construct(private readonly GeoMatcher $matcher) {}

    /**
     * @param  list<array{from: 'customer'|'page', text: string, at: ?string}>  $messages  oldest first
     * @return list<array{from: 'customer'|'page', text: string, at: ?string}>
     */
    public function select(array $messages): array
    {
        $keep = [];
        $customerHit = false;

        foreach ($messages as $i => $message) {
            if (! $this->looksLikeAddress($message['text'])) {
                continue;
            }

            $keep[$i] = true;

            if ($message['from'] === 'customer') {
                $customerHit = true;

                if ($i > 0) {
                    $keep[$i - 1] = true;
                }
            }
        }

        if (! $customerHit) {
            return [];
        }

        ksort($keep);

        return array_slice(
            array_values(array_intersect_key($messages, $keep)),
            -self::MAX_SELECTED,
        );
    }

    public function looksLikeAddress(string $text): bool
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b('.implode('|', self::ADDRESS_WORDS).')\b\.?/u', $lower)) {
            return true;
        }

        // Padded so a name only counts as whole words: "lipa" is not in "pilipinas".
        $normalized = ' '.$this->matcher->normalize($text).' ';

        foreach ($this->matcher->placeNames() as $name) {
            if (str_contains($normalized, ' '.$name.' ')) {
                return true;
            }
        }

        return false;
    }
}
