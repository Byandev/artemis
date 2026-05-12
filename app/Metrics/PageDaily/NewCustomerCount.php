<?php

namespace App\Metrics\PageDaily;

use App\Support\Metrics\OrdersFilter;
use Illuminate\Support\Facades\DB;

final class NewCustomerCount
{
    public function compute(int $workspaceId, array $date_range, array $filter): int
    {
        return (int) $this->baseQuery($workspaceId, $date_range, $filter)
            ->sum('workspace_page_daily_metrics.new_customer_count');
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'daily' => 'workspace_page_daily_metrics.date',
            'weekly' => "DATE_FORMAT(workspace_page_daily_metrics.date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(workspace_page_daily_metrics.date, '%Y-%m')",
            default => 'workspace_page_daily_metrics.date',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql as period, SUM(workspace_page_daily_metrics.new_customer_count) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->selectRaw('pages.id as page_id, pages.name as page_name, SUM(workspace_page_daily_metrics.new_customer_count) as value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, SUM(workspace_page_daily_metrics.new_customer_count) as value')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name, SUM(workspace_page_daily_metrics.new_customer_count) as value')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter, bool $forceJoinPages = false)
    {
        return DB::table('workspace_page_daily_metrics')
            ->tap(fn ($q) => OrdersFilter::joinAndApply($q, $filter, $forceJoinPages, 'workspace_page_daily_metrics'))
            ->where('workspace_page_daily_metrics.workspace_id', $workspaceId)
            ->whereBetween('workspace_page_daily_metrics.date', [
                $date_range['start_date'],
                $date_range['end_date'],
            ]);
    }
}
