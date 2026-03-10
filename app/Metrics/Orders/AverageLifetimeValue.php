<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class AverageLifetimeValue
{
    /**
     * Average LTV  = Total Sales from Customers / # Unique Customers
     *
     * Notes:
     * - Scoped to workspace via pages.workspace_id
     * - Uses CONFIRMED orders only (confirmed_at not null)
     * - Uses final_amount as "sales" (change if you use a different column)
     *
     * @return float currency value
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = DB::table('pancake_orders')
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
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                COALESCE(
                    SUM(pancake_orders.final_amount) / NULLIF(COUNT(DISTINCT pancake_orders.customer_id), 0),
                    0
                ) as avg_lifetime_value
            ')
            ->first();

        return (float) ($row->avg_lifetime_value ?? 0);
    }
}
