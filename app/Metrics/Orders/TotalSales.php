<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class TotalSales
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return (float) $this->baseQuery($workspaceId, $date_range, $filter)
            ->sum('pancake_orders.final_amount');
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%Y-%m')",
            default => "DATE(pancake_orders.confirmed_at)",
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql as period, SUM(pancake_orders.final_amount) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotIn('pancake_orders.status', [6,7]);
    }
}
