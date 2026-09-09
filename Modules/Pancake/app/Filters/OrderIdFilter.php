<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * The one order carrying this id, matched whole.
 *
 * Separate from OrderSearchFilter because that one is a `like` across five
 * columns — right for someone typing into the search box, wrong for a link
 * arriving from elsewhere in the app that already knows which order it means.
 * An id is a short run of digits, so `like %123%` answers with every order and
 * phone number that merely contains it.
 */
class OrderIdFilter implements Filter
{
    use JoinsArrayValues;

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query->where('pancake_orders.id', $this->whole($value));
    }
}
