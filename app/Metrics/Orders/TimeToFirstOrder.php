<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class TimeToFirstOrder
{
    /**
     * Avg time from customer.created_at -> customer's first confirmed order (in DAYS).
     *
     * Assumptions:
     * - pancake_orders.customer_id -> pancake_customers.id
     * - pancake_customers.created_at exists
     *
     * @return float average days
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        // 1) First confirmed order per customer (scoped to workspace)
        $firstOrderPerCustomer = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
            ->whereNotIn('pancake_orders.status', [6,7])
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at
            ');

        // 2) Join customer created_at, then AVG days difference
        $row = DB::query()
            ->fromSub($firstOrderPerCustomer, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->selectRaw('
                COALESCE(
                    AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                    0
                ) as time_to_first_order_hours
            ')
            ->first();

        return (float) ($row->time_to_first_order_hours ?? 0);
    }
}
