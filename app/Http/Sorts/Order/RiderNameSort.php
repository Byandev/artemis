<?php

namespace App\Http\Sorts\Order;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RiderNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('parcel_journeys', 'parcel_journeys.order_id', '=', 'orders.id')
            ->select('orders.*')
            ->orderBy('parcel_journeys.rider_name', $direction);
    }
}
