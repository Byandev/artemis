<?php

namespace App\Http\Sorts\Page;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class OwnerNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('users', 'users.id', '=', 'pages.owner_id')
            ->select('pages.*')
            ->orderBy('users.name', $direction);
    }
}
