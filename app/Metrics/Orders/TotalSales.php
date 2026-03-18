<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class TotalSales
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return round(
            (float) $this->baseQuery($workspaceId, $date_range, $filter)
                ->sum('pancake_orders.final_amount'),
            2
        );
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'daily' => 'DATE(pancake_orders.confirmed_at)',
            'weekly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%Y-%m')",
            default => 'DATE(pancake_orders.confirmed_at)',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql as period, SUM(pancake_orders.final_amount) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                SUM(pancake_orders.final_amount) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('
            shops.id as shop_id,
            shops.name as shop_name,
            SUM(pancake_orders.final_amount) as value
        ')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter, bool $forceJoinPages = false)
    {
        return DB::table('pancake_orders')
            ->when(
                $forceJoinPages || ! empty($filter['page_ids']) || ! empty($filter['shop_ids']),
                function ($query) use ($filter) {
                    $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                        ->when(! empty($filter['page_ids']), function ($query) use ($filter) {
                            $query->whereIn(
                                'pages.id',
                                is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids'])
                            );
                        })
                        ->when(! empty($filter['shop_ids']), function ($query) use ($filter) {
                            $query->whereIn(
                                'pages.shop_id',
                                is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids'])
                            );
                        });
                }
            )
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->whereNotIn('pancake_orders.status', [6, 7]);
    }
}
