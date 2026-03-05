<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class TotalSales
{

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return (float) DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotIn('pancake_orders.status', [6,7])
            ->sum('pancake_orders.final_amount');
    }
}
