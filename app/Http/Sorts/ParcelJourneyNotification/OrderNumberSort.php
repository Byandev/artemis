<?php

namespace App\Http\Sorts\ParcelJourneyNotification;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class OrderNumberSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('orders', 'orders.id', '=', 'parcel_journey_notifications.order_id')
            ->select('parcel_journey_notifications.*')
            ->orderBy('orders.order_number', $direction);
    }
}
