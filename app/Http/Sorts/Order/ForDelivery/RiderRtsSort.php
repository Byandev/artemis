<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class RiderRtsSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('rider_delivery_summary')
                ->select('rts_rate')
                ->whereColumn('rider_name', 'pancake_order_for_delivery.rider_name')
                ->whereColumn('rider_phone', 'pancake_order_for_delivery.rider_phone')
                ->limit(1),

            $descending ? 'desc' : 'asc'
        );
    }
}