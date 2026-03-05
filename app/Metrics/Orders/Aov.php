<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class Aov
{
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
            ->whereNotIn('pancake_orders.status', [6,7])
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*),0), 0) as aov')
            ->first();

        return (float) ($row->aov ?? 0);
    }
}
