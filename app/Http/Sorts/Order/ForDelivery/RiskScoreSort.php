<?php

namespace App\Http\Sorts\Order\ForDelivery;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RiskScoreSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderByRaw(
            self::sql().' '.($descending ? 'DESC' : 'ASC')
        );
    }

    public static function sql(): string
    {
        return '
            LEAST(COALESCE((
                SELECT (delivery_attempts - 1) / 4.0
                FROM pancake_orders
                WHERE id = pancake_order_for_delivery.order_id
                LIMIT 1
            ), 0), 1.0) * 0.75
            + COALESCE((
                SELECT SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                FROM pancake_order_phone_number_reports
                WHERE order_id = pancake_order_for_delivery.order_id
            ), 0) * 0.125
            + COALESCE((
                SELECT cos.rts_rate
                FROM shipping_addresses sa
                JOIN city_order_summaries cos ON cos.district_id = sa.district_id
                WHERE sa.order_id = pancake_order_for_delivery.order_id
                LIMIT 1
            ), 0) * 0.0625
            + COALESCE((
                SELECT rts_rate
                FROM rider_delivery_summary
                WHERE rider_name = pancake_order_for_delivery.rider_name
                AND rider_phone = pancake_order_for_delivery.rider_phone
                LIMIT 1
            ), 0) * 0.0625
        ';
    }
}
