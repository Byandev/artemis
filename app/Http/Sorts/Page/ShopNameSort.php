<?php

namespace App\Http\Sorts\Page;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ShopNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('shops', 'shops.id', '=', 'pages.shop_id')
            ->select('pages.*')
            ->orderBy('shops.name', $direction);
    }
}
