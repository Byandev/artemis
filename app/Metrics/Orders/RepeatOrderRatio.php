<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RepeatOrderRatio
{
    /**
     * Overall repeat-customer rate.
     *
     * @return float ratio 0..1 (multiply by 100 if you want percent)
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt = $dateRange['start_date'];
        $endAt   = $dateRange['end_date'];

        // 1) Cohort customers: at least 1 order inside the window
        $cohort = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6,7])
            ->whereBetween('pancake_orders.confirmed_at', [$startAt, $endAt])
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key');

        // 2) Total confirmed orders per customer up to end date (cumulative)
        $ordersUpToEnd = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.customer_id')
            ->where('pancake_orders.confirmed_at', '<=', $endAt)
            ->whereNotIn('pancake_orders.status', [6,7])
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key, COUNT(*) as orders_count');

        // 3) Join cohort to cumulative counts, then compute ratio
        $row = DB::query()
            ->fromSub($cohort, 'c')
            ->leftJoinSub($ordersUpToEnd, 'o', function ($join) {
                $join->on('o.customer_key', '=', 'c.customer_key');
            })
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN COALESCE(o.orders_count, 0) >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                    0
                ) as repeat_ratio
            ')
            ->first();

        return (float) ($row->repeat_ratio ?? 0);
    }
}
