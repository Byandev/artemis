<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class LocationRtsRateSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('shipping_addresses')
                ->join(
                    'city_order_summaries',
                    'city_order_summaries.district_id',
                    '=',
                    'shipping_addresses.district_id'
                )
                ->select('city_order_summaries.rts_rate')
                ->whereColumn('shipping_addresses.order_id', 'pancake_order_for_delivery.order_id')
                ->limit(1),

            $descending ? 'desc' : 'asc'
        );
    }
}
