<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class AverageDeliveryDays
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                COALESCE(
                    AVG(TIMESTAMPDIFF(DAY, pancake_orders.shipped_at, pancake_orders.delivered_at)),
                    0
                ) as avg_delivery_days
            ')
            ->first();

        return (float) ($row->avg_delivery_days ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.delivered_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.delivered_at, '%Y-%m')",
            default => "DATE(pancake_orders.delivered_at)",
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
                $periodSql as period,
                ROUND(AVG(TIMESTAMPDIFF(DAY, pancake_orders.shipped_at, pancake_orders.delivered_at)), 2) as value
            ")
                    ->groupByRaw($periodSql)
                    ->orderByRaw($periodSql)
                    ->get();
            }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.shipped_at')
            ->whereNotNull('pancake_orders.delivered_at')
            ->whereBetween('pancake_orders.delivered_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ]);
    }
}
