<?php

namespace App\Http\Sorts\Order;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class PageNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('pages', 'pages.id', '=', 'orders.page_id')
            ->select('orders.*')
            ->orderBy('pages.name', $direction);
    }
}
