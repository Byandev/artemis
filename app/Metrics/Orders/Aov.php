<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class Aov
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $query = $this->baseQuery($workspaceId, $date_range, $filter);

        $row = DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->when((isset($filter['page_ids']) && $filter['page_ids']) || (isset($filter['shop_ids']) && $filter['shop_ids']), function ($query) use ($filter) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                    ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                        $query->whereIn('pages.id', explode(',', $filter['page_ids']));
                    })
                    ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                        $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
                    });
            })
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*),0), 0) as aov')
            ->first();

        return (float) ($row->aov ?? 0);
    }
}
