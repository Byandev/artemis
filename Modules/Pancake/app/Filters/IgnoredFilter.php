<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Declared, but narrows nothing.
 *
 * Spatie rejects a filter it was not told about, so a parameter that something
 * else reads still has to appear in the allowed list — the date column is
 * chosen by `date_type`, the return-rate comparison by `rts_op` and its two
 * values. This is how they appear there without also being applied twice.
 *
 * The tab counts reuse it for `status`, so each tab shows its own total instead
 * of the count of the tab already open.
 */
class IgnoredFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query;
    }
}
