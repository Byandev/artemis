<?php

namespace Modules\Finance\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Splitting an amount between the rows charged for it — users on a transaction,
 * products on a fund request, and so on. A row may leave its share blank, in
 * which case it takes an even cut of whatever the explicit shares left over.
 *
 * The arithmetic is done in cents so an uneven split (100 across 3) loses
 * nothing to rounding: the odd centavos go to the first few rows rather than
 * vanishing.
 */
trait SplitsShares
{
    /**
     * The submitted rows of `$field` as `{$key, amount}`, blank shares filled in
     * from the remainder of `$total`. Rows without a value for `$key` are
     * skipped, so a half-filled row on the form is simply not charged.
     *
     * @return list<array<string, mixed>>
     */
    protected function splitShares(string $field, string $key, float $total): array
    {
        $rows = [];

        foreach ((array) $this->input($field, []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = is_string($row[$key] ?? null) ? trim($row[$key]) : ($row[$key] ?? null);

            if ($value === null || $value === '') {
                continue;
            }

            $share = $row['amount'] ?? null;

            $rows[] = [
                $key => $value,
                'cents' => ($share === null || $share === '') ? null : (int) round((float) $share * 100),
            ];
        }

        if ($rows === []) {
            return [];
        }

        $blank = array_keys(array_filter($rows, fn ($r) => $r['cents'] === null));

        if ($blank !== []) {
            $explicit = array_sum(array_column($rows, 'cents'));
            $left = max((int) round($total * 100) - $explicit, 0);
            $each = intdiv($left, count($blank));
            $odd = $left - ($each * count($blank));

            // The leftover cents go to the first few rows rather than vanishing.
            foreach ($blank as $i => $index) {
                $rows[$index]['cents'] = $each + ($i < $odd ? 1 : 0);
            }
        }

        return array_map(fn ($r) => [
            $key => $r[$key],
            'amount' => round($r['cents'] / 100, 2),
        ], $rows);
    }

    /**
     * Add a validation error when a set of shares does not add up to the total —
     * otherwise part of the amount would silently belong to nobody. A caller
     * passing an empty share list opts out (nothing charged/tagged).
     *
     * @param  list<array{amount:float}>  $shares
     */
    protected function assertSharesCoverAmount(
        Validator $validator,
        string $field,
        string $label,
        array $shares,
        float $total,
    ): void {
        if ($shares === []) {
            return;
        }

        $allocated = array_sum(array_column($shares, 'amount'));
        $total = round($total, 2);

        if (abs($allocated - $total) >= 0.01) {
            $validator->errors()->add($field, sprintf(
                'The %s shares add up to %s, but the amount is %s.',
                $label,
                number_format($allocated, 2),
                number_format($total, 2),
            ));
        }
    }
}
