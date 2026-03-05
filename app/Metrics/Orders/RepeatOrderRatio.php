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
    public function compute(int $workspaceId, array $dateRange): float
    {
        $startAt = $dateRange['start_date'];
        $endAt   = $dateRange['end_date'];

        // 1) Cohort customers: at least 1 order inside the window
        $cohort = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.customer_id')
            ->whereBetween('pancake_orders.confirmed_at', [$startAt, $endAt])
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key');

        // 2) Total confirmed orders per customer up to end date (cumulative)
        $ordersUpToEnd = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.customer_id')
            ->where('pancake_orders.confirmed_at', '<=', $endAt)
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
