<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Sorts\Sort;

class CustomerRtsSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy(
            DB::table('pancake_order_phone_number_reports')
                ->selectRaw('
                    SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                ')
                ->whereColumn('order_id', 'pancake_order_for_delivery.order_id'),

            $descending ? 'desc' : 'asc'
        );
    }
}
