<?php

namespace App\Http\Sorts\Checklist;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class TitleNaturalSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->orderByRaw("LOWER(REGEXP_REPLACE(title, '[0-9]+', '')) $direction")
            ->orderByRaw("CAST(REGEXP_SUBSTR(title, '[0-9]+') AS UNSIGNED) $direction")
            ->orderByRaw("LOWER(title) $direction");
    }
}
