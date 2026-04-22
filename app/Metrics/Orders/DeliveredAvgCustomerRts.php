<?php

namespace App\Metrics\Orders;

use App\Support\AnalyticsRollup\ReadsRollup;

final class DeliveredAvgCustomerRts
{
    use ReadsRollup;

    private const SUM = 'sum_customer_rts_rate_delivered';

    private const COUNT = 'delivered_count';

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->selectRaw($this->rollupAvgSql(self::SUM, self::COUNT, 4).' as value')
            ->first();

        return (float) ($row->value ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = $this->rollupPeriodSql($group);

        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql as period, ".$this->rollupAvgSql(self::SUM, self::COUNT, 4).' as value')
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'workspace_daily_metrics.page_id')
            ->selectRaw('pages.id as page_id, pages.name as page_name, '.$this->rollupAvgSql(self::SUM, self::COUNT, 4).' as value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'workspace_daily_metrics.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, '.$this->rollupAvgSql(self::SUM, self::COUNT, 4).' as value')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', 'workspace_daily_metrics.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name, '.$this->rollupAvgSql(self::SUM, self::COUNT, 4).' as value')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }
}
