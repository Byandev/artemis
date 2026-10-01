<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Pancake\Support\CustomerOrderHistory;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Narrow by how many orders the customer has placed in total — the count the
 * Customer orders column shows, out of the phone number's Pancake report.
 *
 * Its own value is the number typed in the first box; the operator and the
 * upper bound of a `between` arrive through the constructor, because a Spatie
 * filter is handed its value and nothing else and `customer_orders_op` /
 * `customer_orders_to` are sibling parameters of this one.
 *
 * A customer with no report behind their number is dropped by every comparison,
 * NULL answering neither side of one: an unknown history is not a history of
 * zero orders, and "first-time buyers" is the `no history` Customer RTS filter
 * rather than a count of none.
 */
class CustomerOrdersFilter implements Filter
{
    use ComparesNumeric;
    use JoinsArrayValues;

    public function __construct(
        private readonly string $operator = '',
        private readonly mixed $upper = null,
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $this->compare(
            $query,
            CustomerOrderHistory::totalSql(),
            $this->operator,
            $this->whole($value),
            $this->upper,
        );
    }
}
