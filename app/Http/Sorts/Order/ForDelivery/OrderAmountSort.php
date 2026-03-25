<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class OrderAmountSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('pancake_orders')
                ->select('final_amount')
                ->whereColumn('pancake_orders.id', 'pancake_order_for_delivery.order_id')
                ->limit(1),
            $descending ? 'desc' : 'asc'
        );
    }
}
