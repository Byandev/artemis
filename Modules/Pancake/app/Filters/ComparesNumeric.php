<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * A single "<expression> <operator> <number>" comparison, typed into a filter
 * chip as an operator and one or two numbers.
 *
 * Shared by the filters that narrow on a computed number rather than a column —
 * the customer's return rate, their lifetime order count — so the rules that
 * are easy to get subtly wrong (an incomplete comparison narrowing nothing, a
 * band typed the wrong way round) hold the same way in both.
 */
trait ComparesNumeric
{
    /**
     * Single-number comparisons, mapped to SQL.
     *
     * An allowlist because the chosen key is interpolated into the comparison;
     * the request names a key, never an operator. `between` is absent because it
     * reads a second number and is built by hand below.
     */
    public const OPERATORS = [
        'gt' => '>',
        'lt' => '<',
        'eq' => '=',
    ];

    /**
     * Narrow `$query` by comparing `$expression` against the number(s) given.
     *
     * A half-typed comparison — a blank box, a `between` missing its upper bound
     * — and an operator that isn't on the allowlist both leave the query alone
     * rather than guessing a bound or reaching the SQL.
     *
     * @param  string  $expression  the SQL being compared, already safe to interpolate
     */
    private function compare(
        Builder $query,
        string $expression,
        string $operator,
        mixed $value,
        mixed $upper = null,
    ): Builder {
        if (! is_numeric($value)) {
            return $query;
        }

        if ($operator === 'between') {
            if (! is_numeric($upper)) {
                return $query;
            }

            // Ordered here rather than trusting the boxes: a range typed high
            // then low is still the range the user meant, and BETWEEN would
            // otherwise quietly match nothing.
            return $query->whereRaw("{$expression} BETWEEN ? AND ?", [
                min((float) $value, (float) $upper),
                max((float) $value, (float) $upper),
            ]);
        }

        $sql = self::OPERATORS[$operator] ?? null;

        if ($sql === null) {
            return $query;
        }

        return $query->whereRaw("{$expression} {$sql} ?", [(float) $value]);
    }
}
