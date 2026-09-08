<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * One status, or several. A tab sends its own; the RTS pages link in with a
 * list (returning,returned), which Spatie has already split by the time it
 * arrives here.
 */
class OrderStatusFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query->whereIn('pancake_orders.status_name', (array) $value);
    }
}
