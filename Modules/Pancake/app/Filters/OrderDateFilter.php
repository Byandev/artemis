<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * One end of the list's date range.
 *
 * The column is chosen by the page's dropdown and resolved against
 * OrderController::DATE_FIELDS before it gets here — the request names a key,
 * never a column, so nothing off the wire reaches the query.
 */
class OrderDateFilter implements Filter
{
    use JoinsArrayValues;

    public function __construct(
        private readonly string $column,
        private readonly string $operator,
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query->whereDate($this->column, $this->operator, $this->whole($value));
    }
}
