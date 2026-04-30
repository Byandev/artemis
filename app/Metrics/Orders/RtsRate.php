<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RtsRate
{
    private const TABLE = 'workspace_page_daily_metrics';

    private const RATIO_SQL = '
        ROUND(
            COALESCE(
                SUM(workspace_page_daily_metrics.entered_returning_amount) /
                NULLIF(SUM(workspace_page_daily_metrics.entered_returning_amount + workspace_page_daily_metrics.delivered_amount), 0),
                0
            ),
            4
        )
    ';

    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw('
                COALESCE(
                    SUM(entered_returning_amount) /
                    NULLIF(SUM(entered_returning_amount + delivered_amount), 0),
                    0
                ) AS rts_rate
            ')
            ->first();

        return (float) ($row->rts_rate ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'DATE(date)',
        };

        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql AS period, ".self::RATIO_SQL.' AS value')
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->selectRaw('pages.id AS page_id, pages.name AS page_name, '.self::RATIO_SQL.' AS value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('shops.id AS shop_id, shops.name AS shop_name, '.self::RATIO_SQL.' AS value')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('users.id AS user_id, users.name AS user_name, '.self::RATIO_SQL.' AS value')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter)
    {
        $query = DB::table(self::TABLE)
            ->where(self::TABLE.'.workspace_id', $workspaceId);

        if (! empty($date_range['start_date']) && ! empty($date_range['end_date'])) {
            $query->whereBetween(self::TABLE.'.date', [
                $date_range['start_date'],
                $date_range['end_date'],
            ]);
        }

        $pageIds = $this->ids($filter, 'page_ids');
        $shopIds = $this->ids($filter, 'shop_ids');
        $userIds = $this->ids($filter, 'user_ids');
        $productIds = $this->ids($filter, 'product_ids');
        $teamIds = $this->ids($filter, 'team_ids');

        if ($pageIds) {
            $query->whereIn(self::TABLE.'.page_id', $pageIds);
        }

        if ($shopIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $shopIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)->whereIn('shop_id', $shopIds);
            });
        }

        if ($userIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $userIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)->whereIn('owner_id', $userIds);
            });
        }

        if ($productIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $productIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)->whereIn('product_id', $productIds);
            });
        }

        if ($teamIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $teamIds) {
                $sub->from('pages')
                    ->select('id')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('owner_id', function ($sub2) use ($teamIds) {
                        $sub2->from('team_user')->select('user_id')->whereIn('team_id', $teamIds);
                    });
            });
        }

        return $query;
    }

    private function ids(array $filter, string $key): ?array
    {
        $value = $filter[$key] ?? null;

        if (empty($value)) {
            return null;
        }

        return is_array($value) ? $value : explode(',', $value);
    }
}
