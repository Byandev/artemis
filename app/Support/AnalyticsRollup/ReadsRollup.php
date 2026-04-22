<?php

namespace App\Support\AnalyticsRollup;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait ReadsRollup
{
    protected function rollupBaseQuery(int $workspaceId, array $dateRange, array $filter): Builder
    {
        $pageIds = ! empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : null;
        $shopIds = ! empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : null;

        return DB::table('workspace_daily_metrics')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateRange['start_date'], $dateRange['end_date']])
            ->when($pageIds, fn ($q) => $q->whereIn('workspace_daily_metrics.page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('workspace_daily_metrics.page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            });
    }

    protected function rollupPeriodSql(string $group): string
    {
        return match ($group) {
            'weekly' => "DATE_FORMAT(workspace_daily_metrics.date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(workspace_daily_metrics.date, '%Y-%m')",
            default => 'workspace_daily_metrics.date',
        };
    }
}
