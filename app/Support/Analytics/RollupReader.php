<?php

namespace App\Support\Analytics;

use Illuminate\Support\Facades\DB;

class RollupReader
{
    public const TABLE = 'workspace_page_daily_metrics';

    public static function canUse(array $filter): bool
    {
        return self::isEmpty($filter, 'product_ids')
            && self::isEmpty($filter, 'team_ids');
    }

    public static function sum(string $column, int $workspaceId, array $dateRange, array $filter): float
    {
        return (float) self::baseQuery($workspaceId, $dateRange, $filter)->sum($column);
    }

    public static function ratio(string $numeratorExpr, string $denominatorExpr, int $workspaceId, array $dateRange, array $filter): float
    {
        $row = self::baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("
                ROUND(
                    (SUM($numeratorExpr) * 100.0) /
                    NULLIF(SUM($denominatorExpr), 0),
                    2
                ) AS value
            ")
            ->first();

        return (float) ($row->value ?? 0);
    }

    public static function divide(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter): float
    {
        $row = self::baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("
                ROUND(
                    SUM($numeratorCol) /
                    NULLIF(SUM($denominatorCol), 0),
                    2
                ) AS value
            ")
            ->first();

        return (float) ($row->value ?? 0);
    }

    public static function breakdown(string $column, int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'DATE(date)',
        };

        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("$periodSql AS period, SUM($column) AS value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public static function ratioBreakdown(string $numeratorExpr, string $denominatorExpr, int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'DATE(date)',
        };

        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->selectRaw("
                $periodSql AS period,
                ROUND(
                    (SUM($numeratorExpr) * 100.0) /
                    NULLIF(SUM($denominatorExpr), 0),
                    2
                ) AS value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public static function perPage(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->selectRaw('
                pages.id AS page_id,
                pages.name AS page_name,
                SUM('.self::TABLE.'.'.$column.') AS value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function perShop(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('
                shops.id AS shop_id,
                shops.name AS shop_name,
                SUM('.self::TABLE.'.'.$column.') AS value
            ')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function perUser(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
                users.id AS user_id,
                users.name AS user_name,
                SUM('.self::TABLE.'.'.$column.') AS value
            ')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function ratioPerPage(string $numeratorExpr, string $denominatorExpr, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->selectRaw("
                pages.id AS page_id,
                pages.name AS page_name,
                ROUND(
                    (SUM($numeratorExpr) * 100.0) /
                    NULLIF(SUM($denominatorExpr), 0),
                    2
                ) AS value
            ")
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function ratioPerShop(string $numeratorExpr, string $denominatorExpr, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw("
                shops.id AS shop_id,
                shops.name AS shop_name,
                ROUND(
                    (SUM($numeratorExpr) * 100.0) /
                    NULLIF(SUM($denominatorExpr), 0),
                    2
                ) AS value
            ")
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function ratioPerUser(string $numeratorExpr, string $denominatorExpr, int $workspaceId, array $dateRange, array $filter)
    {
        return self::baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', self::TABLE.'.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw("
                users.id AS user_id,
                users.name AS user_name,
                ROUND(
                    (SUM($numeratorExpr) * 100.0) /
                    NULLIF(SUM($denominatorExpr), 0),
                    2
                ) AS value
            ")
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private static function baseQuery(int $workspaceId, array $dateRange, array $filter)
    {
        $query = DB::table(self::TABLE)
            ->where(self::TABLE.'.workspace_id', $workspaceId);

        if (! empty($dateRange['start_date']) && ! empty($dateRange['end_date'])) {
            $query->whereBetween(self::TABLE.'.date', [
                $dateRange['start_date'],
                $dateRange['end_date'],
            ]);
        }

        $pageIds = self::normalizeIds($filter['page_ids'] ?? null);
        $shopIds = self::normalizeIds($filter['shop_ids'] ?? null);
        $userIds = self::normalizeIds($filter['user_ids'] ?? null);

        if ($pageIds) {
            $query->whereIn(self::TABLE.'.page_id', $pageIds);
        }

        if ($shopIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $shopIds) {
                $sub->from('pages')
                    ->select('id')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('shop_id', $shopIds);
            });
        }

        if ($userIds) {
            $query->whereIn(self::TABLE.'.page_id', function ($sub) use ($workspaceId, $userIds) {
                $sub->from('pages')
                    ->select('id')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('owner_id', $userIds);
            });
        }

        return $query;
    }

    private static function isEmpty(array $filter, string $key): bool
    {
        $value = $filter[$key] ?? null;

        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    private static function normalizeIds(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
        }

        return array_values(array_filter(explode(',', (string) $value), fn ($v) => $v !== ''));
    }
}
