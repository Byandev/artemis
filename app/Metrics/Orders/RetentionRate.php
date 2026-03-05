<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RetentionRate
{
    /**
     * Overall repeat-customer rate.
     *
     * @return float ratio 0..1 (multiply by 100 if you want percent)
     */
    public function compute(int $workspaceId): float
    {
        $row = DB::query()
            ->fromSub(
                DB::table('pancake_orders')
                    ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                    ->where('pages.workspace_id', $workspaceId)
                    ->whereNotNull('pancake_orders.confirmed_at')
                    ->whereNotNull('pancake_orders.customer_id')
                    ->groupBy('pancake_orders.customer_id')
                    ->selectRaw('pancake_orders.customer_id as customer_key, COUNT(*) as orders_count'),
                't'
            )
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN t.orders_count >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                    0
                ) as retention_rate
            ')
            ->first();

        return (float) ($row->retention_rate ?? 0);
    }
}
