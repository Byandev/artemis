<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RtsRate
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $start = $date_range['start_date'].' 00:00:00';
        $end = $date_range['end_date'].' 23:59:59';

        $pageIds = ! empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : [];

        $shopIds = ! empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : [];

        $returnedQuery = DB::table('pancake_orders')
            ->selectRaw('SUM(pancake_orders.final_amount) as amount, "returned" as type')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.returning_at', [$start, $end])
            ->when(! empty($pageIds) || ! empty($shopIds), function ($query) use ($pageIds, $shopIds) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');

                if (! empty($pageIds)) {
                    $query->whereIn('pages.id', $pageIds);
                }

                if (! empty($shopIds)) {
                    $query->whereIn('pages.shop_id', $shopIds);
                }
            });

        $deliveredQuery = DB::table('pancake_orders')
            ->selectRaw('SUM(pancake_orders.final_amount) as amount, "delivered" as type')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.delivered_at', [$start, $end])
            ->when(! empty($pageIds) || ! empty($shopIds), function ($query) use ($pageIds, $shopIds) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');

                if (! empty($pageIds)) {
                    $query->whereIn('pages.id', $pageIds);
                }

                if (! empty($shopIds)) {
                    $query->whereIn('pages.shop_id', $shopIds);
                }
            });

        $union = $returnedQuery->unionAll($deliveredQuery);

        $row = DB::query()
            ->fromSub($union, 'x')
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN x.type = "returned" THEN x.amount ELSE 0 END)
                    / NULLIF(SUM(x.amount), 0),
                    0
                ) as rts_rate
            ')
            ->first();

        return (float) ($row->rts_rate ?? 0);
    }
}
