<?php

namespace App\Http\Sorts\Order;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class CustomerNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'orders.id')
            ->select('orders.*')
            ->orderBy('shipping_addresses.full_name', $direction);
    }
}
