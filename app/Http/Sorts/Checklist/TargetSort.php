<?php

namespace App\Http\Sorts\Checklist;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * The `target` column is a MySQL ENUM, which orders by internal index rather than
 * the string value. Cast to CHAR so ASC/DESC follow alphabetical order.
 */
class TargetSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query->orderByRaw("CAST(target AS CHAR) $direction");
    }
}
