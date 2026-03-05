<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class AverageDeliveryDays
{
    /**
     * Average Delivery Days = avg(shipped_at -> delivered_at) in DAYS.
     *
     * Notes:
     * - Scoped to workspace via pages.workspace_id
     * - Includes only orders with both shipped_at and delivered_at
     *
     * @return float average days
     */
    public function compute(int $workspaceId, array $date_range): float
    {
        $row = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.shipped_at')
            ->whereNotNull('pancake_orders.delivered_at')
            ->whereDate('pancake_orders.delivered_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.delivered_at', '>=', $date_range['start_date'])
            ->selectRaw('
                COALESCE(
                    AVG(TIMESTAMPDIFF(DAY, pancake_orders.shipped_at, pancake_orders.delivered_at)),
                    0
                ) as avg_delivery_days
            ')
            ->first();

        return (float) ($row->avg_delivery_days ?? 0);
    }
}
