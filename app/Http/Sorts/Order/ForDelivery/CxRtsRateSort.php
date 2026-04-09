<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class CxRtsRateSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderByRaw(
            '(
                SELECT SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                FROM pancake_order_phone_number_reports
                WHERE order_id = pancake_order_for_delivery.order_id
                AND pancake_order_phone_number_reports.type = \'latest\'
            ) IS NULL, (
                SELECT SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                FROM pancake_order_phone_number_reports
                WHERE order_id = pancake_order_for_delivery.order_id
                AND pancake_order_phone_number_reports.type = \'latest\'
            ) '.($descending ? 'DESC' : 'ASC')
        );
    }
}
