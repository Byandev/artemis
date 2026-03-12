<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RepeatOrderRatioCopy
{
    /**
     * Overall repeat-customer rate (0..1)
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        return $this->computeForPeriod($workspaceId, $dateRange, $filter);
    }

    /**
     * Breakdown repeat-customer rate by period (daily/weekly/monthly)
     */
    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%Y-%m')",
            default => 'DATE(pancake_orders.confirmed_at)',
        };

        // 1) Cohort: customers per period
        $cohort = $this->baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("$periodSql as period, pancake_orders.customer_id as customer_key")
            ->groupBy('period', 'pancake_orders.customer_id');

        // 2) Total orders per customer up to period end
        $ordersUpToEnd = $this->baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("pancake_orders.customer_id as customer_key, $periodSql as period, COUNT(*) as orders_count")
            ->groupBy('period', 'pancake_orders.customer_id');

        // 3) Join cohort to counts and calculate repeat ratio per period
        return DB::query()
            ->fromSub($cohort, 'c')
            ->leftJoinSub($ordersUpToEnd, 'o', function ($join) {
                $join->on('o.customer_key', '=', 'c.customer_key')
                    ->on('o.period', '=', 'c.period');
            })
            ->selectRaw('c.period,
                ROUND(COALESCE(
                    SUM(CASE WHEN COALESCE(o.orders_count,0) >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),
                    0
                ) as value, 2)


            ')
            ->groupBy('c.period')
            ->orderBy('c.period')
            ->get();
    }

    /**
     * Helper: compute overall repeat ratio
     */
    private function computeForPeriod(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt = $dateRange['start_date'];
        $endAt = $dateRange['end_date'];

        // Cohort customers
        $cohort = $this->baseQuery($workspaceId, $dateRange, $filter)
            ->whereBetween('pancake_orders.confirmed_at', [$startAt.' 00:00:00', $endAt.' 23:59:59'])
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key');

        // Orders up to end date
        $ordersUpToEnd = $this->baseQuery($workspaceId, $dateRange, $filter)
            ->whereDate('pancake_orders.confirmed_at', '<=', $endAt)
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key, COUNT(*) as orders_count');

        $row = DB::query()
            ->fromSub($cohort, 'c')
            ->leftJoinSub($ordersUpToEnd, 'o', function ($join) {
                $join->on('o.customer_key', '=', 'c.customer_key');
            })
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN COALESCE(o.orders_count,0) >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),
                    0
                ) as repeat_ratio
            ')
            ->first();

        return (float) ($row->repeat_ratio ?? 0);
    }

    /**
     * Base query with workspace and filters
     */
    private function baseQuery(int $workspaceId, array $dateRange, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            });
    }
}
