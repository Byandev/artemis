<?php

namespace Modules\Finance\Statements;

/**
 * Dividing one amount between rows in proportion to a weight.
 *
 * Worked in cents so the shares add back to exactly the amount: the centavos
 * the division leaves over go to the rows that lost the most to rounding,
 * rather than each share being rounded on its own and the total drifting.
 *
 * Used wherever a statement pool is shared out — a product's bought goods
 * across its sellers, the month's OPEX across users or products.
 */
final class ProportionalSplit
{
    /**
     * `$amount` split across `$weights`, keyed the same way.
     *
     * A weightless split (no weights, or all zero) returns nothing: there is
     * no ratio to divide by, and spreading it evenly would invent one. Callers
     * decide what to do with an amount that nothing can carry.
     *
     * @param  array<array-key, float|int>  $weights
     * @return array<array-key, float>
     */
    public static function of(float $amount, array $weights): array
    {
        $positive = array_map(fn ($w) => max((float) $w, 0.0), $weights);
        $total = array_sum($positive);

        if ($total <= 0) {
            return [];
        }

        $cents = (int) round($amount * 100);
        $exact = array_map(fn (float $w) => $cents * $w / $total, $positive);
        $shares = array_map(fn (float $v) => (int) floor($v), $exact);

        $remainders = [];
        foreach ($exact as $key => $value) {
            $remainders[$key] = $value - floor($value);
        }
        arsort($remainders);

        $left = $cents - array_sum($shares);

        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $shares[$key]++;
            $left--;
        }

        return array_map(fn (int $c) => round($c / 100, 2), $shares);
    }
}
