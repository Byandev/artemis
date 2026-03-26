<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class ConferrerNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('pancake_orders')
                ->join('pancake_users', 'pancake_users.id', '=', 'pancake_orders.confirmed_by')
                ->whereColumn('pancake_orders.id', 'pancake_order_for_delivery.order_id')
                ->select('pancake_users.name')
                ->limit(1),

            $descending ? 'desc' : 'asc'
        );
    }
}
