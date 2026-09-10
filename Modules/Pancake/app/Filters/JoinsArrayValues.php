<?php

namespace Modules\Pancake\Filters;

/**
 * Spatie splits a comma-separated filter value into an array before the filter
 * ever sees it. The filters here read one whole value — a rider name or a
 * search phrase may legitimately contain a comma — so the pieces are put back
 * together rather than the filter being handed half a name.
 */
trait JoinsArrayValues
{
    private function whole(mixed $value): string
    {
        return is_array($value) ? implode(',', $value) : (string) $value;
    }
}
