<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class AverageShippedOutDays
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
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.shipped_at')
            ->whereBetween('pancake_orders.shipped_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                COALESCE(
                    AVG(TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.shipped_at)),
                    0
                ) as avg_shipped_out_days
            ')
            ->first();

        return (float) ($row->avg_shipped_out_days ?? 0);
    }
}
