<?php

namespace App\Support\Analytics;

use App\Support\Metrics\OrdersFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Drop-in counterpart to RollupReader that aggregates from pancake_orders directly,
 * skipping the workspace_page_daily_metrics rollup table. Public method shapes mirror
 * RollupReader so metric classes can swap based on a $source toggle.
 *
 * Each rollup column is mapped to a (date_col, expr, extra_where) spec — the same logic
 * that PageDailyMetricsBuilder uses to fill the rollup, expressed against pancake_orders.
 */
class LiveReader
{
    public const TABLE = 'pancake_orders';

    private const STATUS_FILTER = 'pancake_orders.status NOT IN (6, 7)';

    /**
     * Map of rollup column name => spec describing how to derive it from pancake_orders.
     * - date_col: timestamp column the date range filters on
     * - expr: SQL expression aggregated by SUM (use '1' for counts)
     * - extra: additional WHERE clause, joined with AND (or null)
     */
    private const SPEC = [
        'confirmed_count' => ['date_col' => 'confirmed_at', 'expr' => '1', 'extra' => self::STATUS_FILTER],
        'confirmed_amount' => ['date_col' => 'confirmed_at', 'expr' => 'pancake_orders.final_amount', 'extra' => self::STATUS_FILTER],

        'shipped_count' => ['date_col' => 'shipped_at', 'expr' => '1', 'extra' => self::STATUS_FILTER],
        'shipped_amount' => ['date_col' => 'shipped_at', 'expr' => 'pancake_orders.final_amount', 'extra' => self::STATUS_FILTER],

        'delivered_count' => ['date_col' => 'delivered_at', 'expr' => '1', 'extra' => null],
        'delivered_amount' => ['date_col' => 'delivered_at', 'expr' => 'pancake_orders.final_amount', 'extra' => null],

        'entered_returning_count' => ['date_col' => 'returning_at', 'expr' => '1', 'extra' => self::STATUS_FILTER],
        'entered_returning_amount' => ['date_col' => 'returning_at', 'expr' => 'pancake_orders.final_amount', 'extra' => self::STATUS_FILTER],

        'returned_count' => ['date_col' => 'returned_at', 'expr' => '1', 'extra' => null],
        'returned_amount' => ['date_col' => 'returned_at', 'expr' => 'pancake_orders.final_amount', 'extra' => null],

        'sum_days_confirmed_to_shipped' => [
            'date_col' => 'shipped_at',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.shipped_at)',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'count_confirmed_to_shipped' => [
            'date_col' => 'shipped_at',
            'expr' => '1',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'sum_days_confirmed_to_first_attempt' => [
            'date_col' => 'first_delivery_attempt',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.first_delivery_attempt)',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'count_confirmed_to_first_attempt' => [
            'date_col' => 'first_delivery_attempt',
            'expr' => '1',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'sum_days_confirmed_to_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.confirmed_at, pancake_orders.delivered_at)',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'count_confirmed_to_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => '1',
            'extra' => 'pancake_orders.confirmed_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'sum_days_shipped_to_first_attempt' => [
            'date_col' => 'first_delivery_attempt',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.shipped_at, pancake_orders.first_delivery_attempt)',
            'extra' => 'pancake_orders.shipped_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'count_shipped_to_first_attempt' => [
            'date_col' => 'first_delivery_attempt',
            'expr' => '1',
            'extra' => 'pancake_orders.shipped_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'sum_days_shipped_to_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.shipped_at, pancake_orders.delivered_at)',
            'extra' => 'pancake_orders.shipped_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'count_shipped_to_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => '1',
            'extra' => 'pancake_orders.shipped_at IS NOT NULL AND '.self::STATUS_FILTER,
        ],
        'sum_days_returning_to_returned' => [
            'date_col' => 'returned_at',
            'expr' => 'TIMESTAMPDIFF(DAY, pancake_orders.returning_at, pancake_orders.returned_at)',
            'extra' => 'pancake_orders.returning_at IS NOT NULL',
        ],
        'count_returning_to_returned' => [
            'date_col' => 'returned_at',
            'expr' => '1',
            'extra' => 'pancake_orders.returning_at IS NOT NULL',
        ],
        'sum_delivery_attempts_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => 'pancake_orders.delivery_attempts',
            'extra' => 'pancake_orders.delivery_attempts IS NOT NULL',
        ],
        'count_delivery_attempts_delivered' => [
            'date_col' => 'delivered_at',
            'expr' => '1',
            'extra' => 'pancake_orders.delivery_attempts IS NOT NULL',
        ],
        'sum_delivery_attempts_returned' => [
            'date_col' => 'returning_at',
            'expr' => 'pancake_orders.delivery_attempts',
            'extra' => self::STATUS_FILTER.' AND pancake_orders.delivery_attempts IS NOT NULL',
        ],
        'count_delivery_attempts_returned' => [
            'date_col' => 'returning_at',
            'expr' => '1',
            'extra' => self::STATUS_FILTER.' AND pancake_orders.delivery_attempts IS NOT NULL',
        ],
    ];

    public static function sum(string $column, int $workspaceId, array $dateRange, array $filter): float
    {
        $spec = self::spec($column);

        $row = self::baseQuery($workspaceId, $dateRange, $filter, $spec)
            ->selectRaw('COALESCE(SUM('.$spec['expr'].'), 0) AS value')
            ->first();

        return (float) ($row->value ?? 0);
    }

    public static function divide(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter): float
    {
        [$num, $den, $spec] = self::pairExprs($numeratorCol, $denominatorCol);

        $row = self::baseQuery($workspaceId, $dateRange, $filter, $spec)
            ->selectRaw("
                ROUND(
                    SUM($num) /
                    NULLIF(SUM($den), 0),
                    2
                ) AS value
            ")
            ->first();

        return (float) ($row->value ?? 0);
    }

    public static function breakdown(string $column, int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $spec = self::spec($column);
        $periodSql = self::periodSql($spec['date_col'], $group);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec)
            ->selectRaw("$periodSql AS period, COALESCE(SUM(".$spec['expr'].'), 0) AS value')
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public static function divideBreakdown(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        [$num, $den, $spec] = self::pairExprs($numeratorCol, $denominatorCol);
        $periodSql = self::periodSql($spec['date_col'], $group);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec)
            ->selectRaw("
                $periodSql AS period,
                ROUND(
                    SUM($num) /
                    NULLIF(SUM($den), 0),
                    2
                ) AS value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public static function perPage(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        $spec = self::spec($column);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->selectRaw('
                pages.id AS page_id,
                pages.name AS page_name,
                COALESCE(SUM('.$spec['expr'].'), 0) AS value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function perShop(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        $spec = self::spec($column);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->join('shops', 'shops.id', '=', 'pancake_orders.shop_id')
            ->selectRaw('
                shops.id AS shop_id,
                shops.name AS shop_name,
                COALESCE(SUM('.$spec['expr'].'), 0) AS value
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function perUser(string $column, int $workspaceId, array $dateRange, array $filter)
    {
        $spec = self::spec($column);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('
                users.id AS user_id,
                users.name AS user_name,
                COALESCE(SUM('.$spec['expr'].'), 0) AS value
            ')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function dividePerPage(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter)
    {
        [$num, $den, $spec] = self::pairExprs($numeratorCol, $denominatorCol);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->selectRaw("
                pages.id AS page_id,
                pages.name AS page_name,
                ROUND(
                    SUM($num) /
                    NULLIF(SUM($den), 0),
                    2
                ) AS value
            ")
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function dividePerShop(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter)
    {
        [$num, $den, $spec] = self::pairExprs($numeratorCol, $denominatorCol);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw("
                shops.id AS shop_id,
                shops.name AS shop_name,
                ROUND(
                    SUM($num) /
                    NULLIF(SUM($den), 0),
                    2
                ) AS value
            ")
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public static function dividePerUser(string $numeratorCol, string $denominatorCol, int $workspaceId, array $dateRange, array $filter)
    {
        [$num, $den, $spec] = self::pairExprs($numeratorCol, $denominatorCol);

        return self::baseQuery($workspaceId, $dateRange, $filter, $spec, forceJoinPages: true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw("
                users.id AS user_id,
                users.name AS user_name,
                ROUND(
                    SUM($num) /
                    NULLIF(SUM($den), 0),
                    2
                ) AS value
            ")
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * @param  array{date_col:string,expr:string,extra:?string}  $spec
     */
    private static function baseQuery(int $workspaceId, array $dateRange, array $filter, array $spec, bool $forceJoinPages = false): Builder
    {
        $query = DB::table(self::TABLE)
            ->where('pancake_orders.workspace_id', $workspaceId);

        if (! empty($dateRange['start_date']) && ! empty($dateRange['end_date'])) {
            $query->whereBetween('pancake_orders.'.$spec['date_col'], [
                $dateRange['start_date'].' 00:00:00',
                $dateRange['end_date'].' 23:59:59',
            ]);
        } else {
            $query->whereNotNull('pancake_orders.'.$spec['date_col']);
        }

        if ($spec['extra']) {
            $query->whereRaw($spec['extra']);
        }

        OrdersFilter::joinAndApply($query, $filter, $forceJoinPages);

        return $query;
    }

    private static function periodSql(string $dateCol, string $group): string
    {
        $col = 'pancake_orders.'.$dateCol;

        return match ($group) {
            'weekly' => "DATE_FORMAT($col, '%x-W%v')",
            'monthly' => "DATE_FORMAT($col, '%Y-%m')",
            default => "DATE($col)",
        };
    }

    /**
     * Resolve the SQL expressions for a divide pair. Both columns must share the same
     * date_col and extra clause — true for every divide pair currently in use.
     *
     * @return array{0:string,1:string,2:array{date_col:string,expr:string,extra:?string}}
     */
    private static function pairExprs(string $numCol, string $denCol): array
    {
        $numSpec = self::spec($numCol);
        $denSpec = self::spec($denCol);

        if ($numSpec['date_col'] !== $denSpec['date_col'] || $numSpec['extra'] !== $denSpec['extra']) {
            throw new \InvalidArgumentException(
                "LiveReader divide pair ($numCol, $denCol) has mismatched date_col or extra; ".
                'split into separate queries or extend LiveReader with CASE-WHEN support.'
            );
        }

        return [$numSpec['expr'], $denSpec['expr'], $numSpec];
    }

    /**
     * @return array{date_col:string,expr:string,extra:?string}
     */
    private static function spec(string $column): array
    {
        if (! isset(self::SPEC[$column])) {
            throw new \InvalidArgumentException("LiveReader has no spec for column '$column'.");
        }

        return self::SPEC[$column];
    }
}
