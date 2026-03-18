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

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $start = $date_range['start_date'].' 00:00:00';
        $end = $date_range['end_date'].' 23:59:59';

        $pageIds = ! empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : [];

        $shopIds = ! empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : [];

        $returnedPeriodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.returning_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.returning_at, '%Y-%m')",
            default => 'DATE(pancake_orders.returning_at)',
        };

        $deliveredPeriodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.delivered_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.delivered_at, '%Y-%m')",
            default => 'DATE(pancake_orders.delivered_at)',
        };

        $returnedQuery = DB::table('pancake_orders')
            ->selectRaw("
                $returnedPeriodSql as period,
                SUM(pancake_orders.final_amount) as amount,
                'returned' as type
            ")
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
            })
            ->groupByRaw($returnedPeriodSql);

        $deliveredQuery = DB::table('pancake_orders')
            ->selectRaw("
                $deliveredPeriodSql as period,
                SUM(pancake_orders.final_amount) as amount,
                'delivered' as type
            ")
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
            })
            ->groupByRaw($deliveredPeriodSql);

        $union = $returnedQuery->unionAll($deliveredQuery);

        return DB::query()
            ->fromSub($union, 'x')
            ->selectRaw('
                x.period,
                ROUND(
                    COALESCE(
                        SUM(CASE WHEN x.type = "returned" THEN x.amount ELSE 0 END)
                        / NULLIF(SUM(x.amount), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('x.period')
            ->orderBy('x.period')
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
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
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                SUM(pancake_orders.final_amount) as amount,
                "returned" as type
            ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.returning_at', [$start, $end])
            ->when(! empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->groupBy('pages.id', 'pages.name');

        $deliveredQuery = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                SUM(pancake_orders.final_amount) as amount,
                "delivered" as type
            ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.delivered_at', [$start, $end])
            ->when(! empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->groupBy('pages.id', 'pages.name');

        $union = $returnedQuery->unionAll($deliveredQuery);

        return DB::query()
            ->fromSub($union, 'x')
            ->selectRaw('
                x.page_id,
                x.page_name,
                ROUND(
                    COALESCE(
                        SUM(CASE WHEN x.type = "returned" THEN x.amount ELSE 0 END)
                        / NULLIF(SUM(x.amount), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('x.page_id', 'x.page_name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
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
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                SUM(pancake_orders.final_amount) as amount,
                "returned" as type
            ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.returning_at', [$start, $end])
            ->when(! empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name');

        $deliveredQuery = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                SUM(pancake_orders.final_amount) as amount,
                "delivered" as type
            ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.delivered_at', [$start, $end])
            ->when(! empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name');

        $union = $returnedQuery->unionAll($deliveredQuery);

        return DB::query()
            ->fromSub($union, 'x')
            ->selectRaw('
                x.shop_id,
                x.shop_name,
                ROUND(
                    COALESCE(
                        SUM(CASE WHEN x.type = "returned" THEN x.amount ELSE 0 END)
                        / NULLIF(SUM(x.amount), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('x.shop_id', 'x.shop_name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        $start = $date_range['start_date'] . ' 00:00:00';
        $end = $date_range['end_date'] . ' 23:59:59';

        $pageIds = !empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : [];

        $shopIds = !empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : [];

        $returnedQuery = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
            users.id as user_id,
            users.name as user_name,
            SUM(pancake_orders.final_amount) as amount,
            "returned" as type
        ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.returning_at', [$start, $end])
            ->when(!empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(!empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name');

        $deliveredQuery = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
            users.id as user_id,
            users.name as user_name,
            SUM(pancake_orders.final_amount) as amount,
            "delivered" as type
        ')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->whereBetween('pancake_orders.delivered_at', [$start, $end])
            ->when(!empty($pageIds), function ($query) use ($pageIds) {
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(!empty($shopIds), function ($query) use ($shopIds) {
                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name');

        $union = $returnedQuery->unionAll($deliveredQuery);

        return DB::query()
            ->fromSub($union, 'x')
            ->selectRaw('
            x.user_id,
            x.user_name,
            ROUND(
                COALESCE(
                    SUM(CASE WHEN x.type = "returned" THEN x.amount ELSE 0 END)
                    / NULLIF(SUM(x.amount), 0),
                    0
                ),
                4
            ) as value
        ')
            ->groupBy('x.user_id', 'x.user_name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(isset($filter['page_ids']) && $filter['page_ids'], function ($query) use ($filter) {
                $pageIds = is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']);
                $query->whereIn('pages.id', $pageIds);
            })
            ->when(isset($filter['shop_ids']) && $filter['shop_ids'], function ($query) use ($filter) {
                $shopIds = is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']);
                $query->whereIn('pages.shop_id', $shopIds);
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
