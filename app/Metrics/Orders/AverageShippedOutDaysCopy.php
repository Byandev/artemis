<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class AverageShippedOutDaysCopy
{
    /**
     * Compute average days from confirmed -> shipped
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.shipped_at)),
                        0
                    ), 2
                ) as avg_shipped_out_days
            ')
            ->first();

        return (float) ($row->avg_shipped_out_days ?? 0);
    }

    /**
     * Breakdown average shipped out days by period
     */
    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.shipped_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.shipped_at, '%Y-%m')",
            default => 'DATE(pancake_orders.shipped_at)',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
                $periodSql as period,
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.shipped_at)),
                        0
                    ), 2
                ) as value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    /**
     * Base query to avoid duplicating filters
     */
    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotNull('pancake_orders.shipped_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.shipped_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            });
    }
}
