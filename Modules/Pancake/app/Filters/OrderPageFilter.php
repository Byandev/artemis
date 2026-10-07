<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * One Facebook page, or several. Spatie has already split a comma-separated
 * list by the time it arrives here, and page ids never contain a comma.
 */
class OrderPageFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query->whereIn('pancake_orders.page_id', (array) $value);
    }
}
