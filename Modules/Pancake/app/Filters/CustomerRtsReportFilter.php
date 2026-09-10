<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Pancake\Support\CustomerRtsRisk;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Narrow by the customer's return report — the same rate the Customer RTS
 * column shows.
 *
 * `no_report` is the orders whose phone number has nothing behind it, which is
 * exactly where the rate comes back NULL. `has_report` is the rest, and may
 * carry a comparison against the number(s) typed beside the operator.
 *
 * The comparison arrives through the constructor rather than off the request: a
 * Spatie filter is handed its own value and nothing else, and `rts_op` /
 * `rts_value` / `rts_value2` are three sibling parameters of this one.
 *
 * Compared as a whole percent, because that is what the badge shows: a row
 * reading 30% should answer a "= 30" rather than nothing at all.
 */
class CustomerRtsReportFilter implements Filter
{
    use JoinsArrayValues;

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

    public function __construct(
        private readonly string $operator = '',
        private readonly mixed $value = null,
        private readonly mixed $upper = null,
    ) {}

    public function __invoke(Builder $query, mixed $report, string $property): Builder
    {
        $rate = CustomerRtsRisk::rateSql();

        if ($this->whole($report) === 'no_report') {
            return $query->whereRaw("{$rate} IS NULL");
        }

        $query->whereRaw("{$rate} IS NOT NULL");

        // Half a comparison narrows nothing — the report filter still stands.
        if (! is_numeric($this->value)) {
            return $query;
        }

        $percent = "ROUND({$rate} * 100)";

        if ($this->operator === 'between') {
            if (! is_numeric($this->upper)) {
                return $query;
            }

            // Ordered here rather than trusting the boxes: a range typed high
            // then low is still the range the user meant, and BETWEEN would
            // otherwise quietly match nothing.
            return $query->whereRaw("{$percent} BETWEEN ? AND ?", [
                min((float) $this->value, (float) $this->upper),
                max((float) $this->value, (float) $this->upper),
            ]);
        }

        $sql = self::OPERATORS[$this->operator] ?? null;

        if ($sql === null) {
            return $query;
        }

        return $query->whereRaw("{$percent} {$sql} ?", [(float) $this->value]);
    }
}
