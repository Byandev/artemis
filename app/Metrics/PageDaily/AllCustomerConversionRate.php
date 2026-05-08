<?php

namespace App\Metrics\PageDaily;

use App\Support\Analytics\AllCustomerConversionRate as RateMath;
use App\Support\Metrics\OrdersFilter;
use Illuminate\Support\Facades\DB;

final class AllCustomerConversionRate
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('SUM(workspace_page_daily_metrics.confirmed_count) as orders, SUM(workspace_page_daily_metrics.all_customer_count) as customers')
            ->first();

        return RateMath::compute((int) ($row->orders ?? 0), (int) ($row->customers ?? 0));
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
            ->selectRaw("$periodSql as period,
                SUM(workspace_page_daily_metrics.confirmed_count) as orders,
                SUM(workspace_page_daily_metrics.all_customer_count) as customers")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get()
            ->map(fn ($r) => (object) [
                'period' => $r->period,
                'value' => RateMath::compute((int) $r->orders, (int) $r->customers),
            ]);
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->selectRaw('pages.id as page_id, pages.name as page_name,
                SUM(workspace_page_daily_metrics.confirmed_count) as orders,
                SUM(workspace_page_daily_metrics.all_customer_count) as customers')
            ->groupBy('pages.id', 'pages.name')
            ->get()
            ->map(fn ($r) => (object) [
                'page_id' => $r->page_id,
                'page_name' => $r->page_name,
                'value' => RateMath::compute((int) $r->orders, (int) $r->customers),
            ])
            ->sortByDesc('value')
            ->values();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name,
                SUM(workspace_page_daily_metrics.confirmed_count) as orders,
                SUM(workspace_page_daily_metrics.all_customer_count) as customers')
            ->groupBy('shops.id', 'shops.name')
            ->get()
            ->map(fn ($r) => (object) [
                'shop_id' => $r->shop_id,
                'shop_name' => $r->shop_name,
                'value' => RateMath::compute((int) $r->orders, (int) $r->customers),
            ])
            ->sortByDesc('value')
            ->values();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name,
                SUM(workspace_page_daily_metrics.confirmed_count) as orders,
                SUM(workspace_page_daily_metrics.all_customer_count) as customers')
            ->groupBy('users.id', 'users.name')
            ->get()
            ->map(fn ($r) => (object) [
                'user_id' => $r->user_id,
                'user_name' => $r->user_name,
                'value' => RateMath::compute((int) $r->orders, (int) $r->customers),
            ])
            ->sortByDesc('value')
            ->values();
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
