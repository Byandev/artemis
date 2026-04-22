<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class UniqueCustomerCount
{
    public function compute(int $workspaceId, array $date_range, array $filter): int
    {
        return (int) $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('COUNT(DISTINCT a.customer_id) as total')
            ->value('total');
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(a.date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(a.date, '%Y-%m')",
            default => 'a.date',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql as period, COUNT(DISTINCT a.customer_id) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->selectRaw('pages.id as page_id, pages.name as page_name, COUNT(DISTINCT a.customer_id) as value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, COUNT(DISTINCT a.customer_id) as value')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name, COUNT(DISTINCT a.customer_id) as value')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        $pageIds = ! empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : null;
        $shopIds = ! empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : null;

        return DB::table('workspace_customer_activity_daily as a')
            ->where('a.workspace_id', $workspaceId)
            ->whereBetween('a.date', [$date_range['start_date'], $date_range['end_date']])
            ->when($pageIds, fn ($q) => $q->whereIn('a.page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('a.page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            });
    }
}
