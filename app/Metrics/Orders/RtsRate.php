<?php

namespace App\Metrics\Orders;

use App\Metrics\Concerns\HasMetricSource;
use App\Metrics\MetricSource;
use App\Support\Metrics\OrdersFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class RtsRate
{
    use HasMetricSource;

    private const ROLLUP_TABLE = 'workspace_page_daily_metrics';

    private const ROLLUP_RATIO_SQL = '
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
        if ($this->source === MetricSource::LIVE) {
            $row = $this->liveBaseQuery($workspaceId, $date_range, $filter)
                ->selectRaw($this->liveRatioSql().' AS rts_rate')
                ->first();

            return (float) ($row->rts_rate ?? 0);
        }

        $row = $this->rollupBaseQuery($workspaceId, $date_range, $filter)
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
        if ($this->source === MetricSource::LIVE) {
            return $this->liveBreakdown($workspaceId, $date_range, $filter, $group);
        }

        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'DATE(date)',
        };

        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->selectRaw("$periodSql AS period, ".self::ROLLUP_RATIO_SQL.' AS value')
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        if ($this->source === MetricSource::LIVE) {
            return $this->liveBaseQuery($workspaceId, $date_range, $filter, forceJoinPages: true)
                ->selectRaw('pages.id AS page_id, pages.name AS page_name, '.$this->liveRatioSql().' AS value')
                ->groupBy('pages.id', 'pages.name')
                ->orderByDesc('value')
                ->get();
        }

        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::ROLLUP_TABLE.'.page_id')
            ->selectRaw('pages.id AS page_id, pages.name AS page_name, '.self::ROLLUP_RATIO_SQL.' AS value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        if ($this->source === MetricSource::LIVE) {
            return $this->liveBaseQuery($workspaceId, $date_range, $filter, forceJoinPages: true)
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->selectRaw('shops.id AS shop_id, shops.name AS shop_name, '.$this->liveRatioSql().' AS value')
                ->whereNotNull('pages.shop_id')
                ->groupBy('shops.id', 'shops.name')
                ->orderByDesc('value')
                ->get();
        }

        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::ROLLUP_TABLE.'.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('shops.id AS shop_id, shops.name AS shop_name, '.self::ROLLUP_RATIO_SQL.' AS value')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        if ($this->source === MetricSource::LIVE) {
            return $this->liveBaseQuery($workspaceId, $date_range, $filter, forceJoinPages: true)
                ->join('users', 'users.id', '=', 'pages.owner_id')
                ->selectRaw('users.id AS user_id, users.name AS user_name, '.$this->liveRatioSql().' AS value')
                ->whereNotNull('pages.owner_id')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('value')
                ->get();
        }

        return $this->rollupBaseQuery($workspaceId, $date_range, $filter)
            ->join('pages', 'pages.id', '=', self::ROLLUP_TABLE.'.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('users.id AS user_id, users.name AS user_name, '.self::ROLLUP_RATIO_SQL.' AS value')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function rollupBaseQuery(int $workspaceId, array $date_range, array $filter): Builder
    {
        $query = DB::table(self::ROLLUP_TABLE)
            ->where(self::ROLLUP_TABLE.'.workspace_id', $workspaceId);

        if (! empty($date_range['start_date']) && ! empty($date_range['end_date'])) {
            $query->whereBetween(self::ROLLUP_TABLE.'.date', [
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
            $query->whereIn(self::ROLLUP_TABLE.'.page_id', $pageIds);
        }

        if ($shopIds) {
            $query->whereIn(self::ROLLUP_TABLE.'.page_id', function ($sub) use ($workspaceId, $shopIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)->whereIn('shop_id', $shopIds);
            });
        }

        if ($userIds) {
            $query->whereIn(self::ROLLUP_TABLE.'.page_id', function ($sub) use ($workspaceId, $userIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)->whereIn('owner_id', $userIds);
            });
        }

        if ($productIds) {
            $query->whereIn(self::ROLLUP_TABLE.'.page_id', function ($sub) use ($workspaceId, $productIds) {
                $sub->from('pages')->select('id')->where('workspace_id', $workspaceId)
                    ->whereIn('shop_id', function ($sub2) use ($workspaceId, $productIds) {
                        $sub2->from('shops')->select('id')->where('workspace_id', $workspaceId)->whereIn('product_id', $productIds);
                    });
            });
        }

        if ($teamIds) {
            $query->whereIn(self::ROLLUP_TABLE.'.page_id', function ($sub) use ($workspaceId, $teamIds) {
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

    /**
     * Live base query: select rows where either the returning event or the delivered
     * event falls within the date range. The CASE-WHEN ratio expression handles which
     * column each row contributes to.
     */
    private function liveBaseQuery(int $workspaceId, array $date_range, array $filter, bool $forceJoinPages = false): Builder
    {
        $start = $date_range['start_date'].' 00:00:00';
        $end = $date_range['end_date'].' 23:59:59';

        $query = DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->whereBetween('pancake_orders.returning_at', [$start, $end])
                        ->whereNotIn('pancake_orders.status', [6, 7]);
                })->orWhereBetween('pancake_orders.delivered_at', [$start, $end]);
            });

        OrdersFilter::joinAndApply($query, $filter, $forceJoinPages);

        return $query;
    }

    /**
     * Live ratio: returning amount / (returning amount + delivered amount).
     * Each row contributes to at most one of the two sums based on which timestamp
     * falls inside the date range.
     *
     * Date-range bind values are expected to be already constrained on the surrounding
     * WHERE (see liveBaseQuery), so this expression conservatively re-asserts the
     * conditions inside CASE-WHEN to avoid counting a row's amount twice if both
     * returning_at and delivered_at fall in range.
     */
    private function liveRatioSql(): string
    {
        return '
            ROUND(
                COALESCE(
                    SUM(CASE
                        WHEN pancake_orders.returning_at IS NOT NULL
                            AND pancake_orders.status NOT IN (6, 7)
                        THEN pancake_orders.final_amount
                        ELSE 0
                    END)
                    /
                    NULLIF(
                        SUM(CASE
                            WHEN pancake_orders.returning_at IS NOT NULL
                                AND pancake_orders.status NOT IN (6, 7)
                            THEN pancake_orders.final_amount
                            ELSE 0
                        END)
                        +
                        SUM(CASE
                            WHEN pancake_orders.delivered_at IS NOT NULL
                            THEN pancake_orders.final_amount
                            ELSE 0
                        END),
                        0
                    ),
                    0
                ),
                4
            )
        ';
    }

    /**
     * Breakdown by period for live mode. Uses a UNION ALL of the two events so the
     * ratio can be computed per period — an order with returning_at on day X and
     * delivered_at on day Y contributes to both days like the rollup does.
     */
    private function liveBreakdown(int $workspaceId, array $date_range, array $filter, string $group)
    {
        $start = $date_range['start_date'].' 00:00:00';
        $end = $date_range['end_date'].' 23:59:59';

        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(t.event_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(t.event_at, '%Y-%m')",
            default => 'DATE(t.event_at)',
        };

        $returningQuery = DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereBetween('pancake_orders.returning_at', [$start, $end])
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->selectRaw('pancake_orders.returning_at AS event_at, pancake_orders.final_amount AS returning_amount, 0 AS delivered_amount');

        OrdersFilter::joinAndApply($returningQuery, $filter);

        $deliveredQuery = DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereBetween('pancake_orders.delivered_at', [$start, $end])
            ->selectRaw('pancake_orders.delivered_at AS event_at, 0 AS returning_amount, pancake_orders.final_amount AS delivered_amount');

        OrdersFilter::joinAndApply($deliveredQuery, $filter);

        return DB::query()
            ->fromSub($returningQuery->unionAll($deliveredQuery), 't')
            ->selectRaw("
                $periodSql AS period,
                ROUND(
                    COALESCE(
                        SUM(t.returning_amount) /
                        NULLIF(SUM(t.returning_amount) + SUM(t.delivered_amount), 0),
                        0
                    ),
                    4
                ) AS value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
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
