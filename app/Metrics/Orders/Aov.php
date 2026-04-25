<?php

namespace App\Metrics\Orders;

use App\Support\Analytics\RollupReader;
use App\Support\Metrics\OrdersFilter;
use Illuminate\Support\Facades\DB;

final class Aov
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        if (RollupReader::canUse($filter)) {
            return RollupReader::divide('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
        }

        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*), 0), 0) as value
            ')
            ->first();

        return round((float) ($row->value ?? 0), 2);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        if (RollupReader::canUse($filter)) {
            return RollupReader::ratioBreakdown('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter, $group);
        }

        $periodSql = match ($group) {
            'daily' => 'DATE(pancake_orders.confirmed_at)',
            'weekly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%Y-%m')",
            default => 'DATE(pancake_orders.confirmed_at)',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("
                $periodSql as period,
                ROUND(
                    COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*), 0), 0),
                    2
                ) as value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        if (RollupReader::canUse($filter)) {
            return RollupReader::ratioPerPage('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
        }

        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                ROUND(
                    COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*), 0), 0),
                    2
                ) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        if (RollupReader::canUse($filter)) {
            return RollupReader::ratioPerShop('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
        }

        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('
            shops.id as shop_id,
            shops.name as shop_name,
            ROUND(
                COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*), 0), 0),
                2
            ) as value
        ')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        if (RollupReader::canUse($filter)) {
            return RollupReader::ratioPerUser('confirmed_amount', 'confirmed_count', $workspaceId, $date_range, $filter);
        }

        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
            users.id as user_id,
            users.name as user_name,
            ROUND(
                COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*), 0), 0),
                2
            ) as value
        ')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(
        int $workspaceId,
        array $date_range,
        array $filter,
        bool $forceJoinPages = false
    ) {
        return DB::table('pancake_orders')
            ->tap(fn ($q) => OrdersFilter::joinAndApply($q, $filter, $forceJoinPages))
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereBetween('pancake_orders.confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->whereNotIn('pancake_orders.status', [6, 7]);
    }
}
