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
    public function compute(int $workspaceId, array $date_range): float
    {
        $row = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.customer_id')
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
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
