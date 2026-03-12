<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class TimeToFirstOrderCopy
{
    /**
     * Avg time from customer.created_at -> customer's first confirmed order (in HOURS)
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $firstOrderPerCustomer = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at
            ')
            ->groupBy('pancake_orders.customer_id');

        return round(
            (float) (
                DB::query()
                    ->fromSub($firstOrderPerCustomer, 't')
                    ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
                    ->whereNotNull('c.created_at')
                    ->selectRaw('
                        COALESCE(
                            AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                            0
                        ) as time_to_first_order_hours
                    ')
                    ->value('time_to_first_order_hours') ?? 0
            ),
            2
        );
    }

    /**
     * Breakdown avg time to first order by period
     */
    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(MIN(pancake_orders.confirmed_at), '%x-W%v')",
            'monthly' => "DATE_FORMAT(MIN(pancake_orders.confirmed_at), '%Y-%m')",
            default => 'DATE(MIN(pancake_orders.confirmed_at))',
        };

        $firstOrderPerCustomer = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at,
                {$periodSql} as period
            ")
            ->groupBy('pancake_orders.customer_id');

        return DB::query()
            ->fromSub($firstOrderPerCustomer, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->selectRaw('
                t.period,
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                        0
                    ),
                    2
                ) as value
            ')
            ->groupBy('t.period')
            ->orderBy('t.period')
            ->get();
    }

    /**
     * Base query for workspace + filters + confirmed orders
     */
    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            });
    }
}
