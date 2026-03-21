<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class ReturnedAvgCustomerRts
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return round(
            (float) $this->baseQuery($workspaceId, $date_range, $filter)
                ->selectRaw('AVG(COALESCE(phone_reports.customer_rts_rate, 0)) as value')
                ->value('value'),
            4
        );
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'daily' => 'DATE(pancake_orders.returning_at)',
            'weekly' => "DATE_FORMAT(pancake_orders.returning_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.returning_at, '%Y-%m')",
            default => 'DATE(pancake_orders.returning_at)',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
                {$periodSql} as period,
                AVG(COALESCE(phone_reports.customer_rts_rate, 0)) as value
            ")
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
                AVG(COALESCE(phone_reports.customer_rts_rate, 0)) as value
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
                AVG(COALESCE(phone_reports.customer_rts_rate, 0)) as value
            ')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                AVG(COALESCE(phone_reports.customer_rts_rate, 0)) as value
            ')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter, bool $forceJoinPages = false)
    {
        $phoneReportsSub = DB::table('pancake_order_phone_number_reports')
            ->selectRaw('
                order_id,
                COALESCE(
                    SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0),
                    0
                ) as customer_rts_rate
            ')
            ->groupBy('order_id');

        return DB::table('pancake_orders')
            ->leftJoinSub($phoneReportsSub, 'phone_reports', function ($join) {
                $join->on('phone_reports.order_id', '=', 'pancake_orders.id');
            })
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
            ->whereNotNull('pancake_orders.returning_at')
            ->whereBetween('pancake_orders.returning_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ]);
    }
}
