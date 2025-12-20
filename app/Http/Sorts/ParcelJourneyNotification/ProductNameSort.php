<?php

namespace App\Http\Sorts\ParcelJourneyNotification;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->leftJoin('orders', 'orders.id', '=', 'parcel_journey_notifications.order_id')
            ->leftJoin('pages', 'pages.id', '=', 'orders.page_id')
            ->leftJoin('products', 'products.id', '=', 'pages.product_id')
            ->select('parcel_journey_notifications.*')
            ->orderBy('products.name', $direction);
    }
}
