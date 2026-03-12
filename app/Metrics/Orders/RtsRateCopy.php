<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RtsRateCopy
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                COALESCE(
                    (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                    / NULLIF(
                        (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                        + (SUM(CASE WHEN pancake_orders.delivered_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END)),
                        0
                    ),
                    0
                ) as rts_rate
            ')
            ->first();


        return (float) ($row->rts_rate ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'monthly')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(COALESCE(pancake_orders.returning_at, pancake_orders.delivered_at), '%x-W%v')",
            'monthly' => "DATE_FORMAT(COALESCE(pancake_orders.returning_at, pancake_orders.delivered_at), '%Y-%m')",
            default => "DATE(COALESCE(pancake_orders.returning_at, pancake_orders.delivered_at))",
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
            $periodSql as period,
            ROUND(
                COALESCE(
                    (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                    / NULLIF(
                        (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                        + (SUM(CASE WHEN pancake_orders.delivered_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END)),
                        0
                    ),
                    0
                ),
            2) as value
        ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->where(function ($query) use ($date_range) {
                $query->orWhereBetween('pancake_orders.returning_at', [
                    $date_range['start_date'].' 00:00:00',
                    $date_range['end_date'].' 23:59:59',
                ])
                    ->orWhereBetween('pancake_orders.delivered_at', [
                        $date_range['start_date'].' 00:00:00',
                        $date_range['end_date'].' 23:59:59',
                    ]);
            });
    }
}
