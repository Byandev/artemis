<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class CustomerNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('pancake_orders')
                ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id')
                ->selectRaw('COALESCE(shipping_addresses.full_name, "")')
                ->whereColumn('pancake_orders.id', 'pancake_order_for_delivery.order_id')
                ->limit(1),
            $descending ? 'desc' : 'asc'
        );
    }
}
