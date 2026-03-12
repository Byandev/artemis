<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AverageLifetimeValue
{
    /**
     * Average lifetime value up to selected end date
     *
     * Formula:
     * total confirmed sales up to end date / unique confirmed customers up to end date
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])
            ->addDay()
            ->startOfDay()
            ->toDateTimeString();

        $salesQuery = DB::query()
            ->fromSub(
                $this->baseOrdersQuery(
                    $workspaceId,
                    $filter,
                    $endExclusive,
                    'idx_orders_workspace_confirmed_customer_amount'
                )->selectRaw('SUM(pancake_orders.final_amount) as total_sales'),
                's'
            );

        $customersQuery = DB::query()->fromSub(
            $this->baseOrdersQuery(
                $workspaceId,
                $filter,
                $endExclusive,
                'idx_orders_workspace_customer_confirmed'
            )
                ->select('pancake_orders.customer_id')
                ->groupBy('pancake_orders.customer_id'),
            'x'
        )->selectRaw('COUNT(*) as total_customers');

        $row = $salesQuery
            ->crossJoinSub($customersQuery, 'c')
            ->selectRaw('
                COALESCE(
                    s.total_sales * 1.0 / NULLIF(c.total_customers, 0),
                    0
                ) as avg_lifetime_value
            ')
            ->first();

        return round((float) ($row->avg_lifetime_value ?? 0), 2);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $startInclusive = Carbon::parse($dateRange['start_date'])
            ->startOfDay()
            ->toDateTimeString();

        $endExclusive = Carbon::parse($dateRange['end_date'])
            ->addDay()
            ->startOfDay()
            ->toDateTimeString();

        $periodSql = $this->periodSql('pancake_orders.confirmed_at', $group);
        $firstOrderPeriodSql = $this->periodSql('t.first_confirmed_at', $group);

        $baselineSales = (float) (
            $this->baseOrdersBetweenQuery(
                $workspaceId,
                $filter,
                null,
                $startInclusive,
                'idx_orders_workspace_confirmed_customer_amount'
            )
                ->selectRaw('COALESCE(SUM(pancake_orders.final_amount), 0) as total_sales')
                ->value('total_sales') ?? 0
        );

        $baselineCustomers = (int) (
            DB::query()
                ->fromSub(
                    $this->baseOrdersBetweenQuery(
                        $workspaceId,
                        $filter,
                        null,
                        $startInclusive,
                        'idx_orders_workspace_customer_confirmed'
                    )
                        ->select('pancake_orders.customer_id')
                        ->groupBy('pancake_orders.customer_id'),
                    'x'
                )
                ->selectRaw('COUNT(*) as total_customers')
                ->value('total_customers') ?? 0
        );

        $salesByPeriod = $this->baseOrdersBetweenQuery(
            $workspaceId,
            $filter,
            $startInclusive,
            $endExclusive,
            'idx_orders_workspace_confirmed_customer_amount'
        )
            ->selectRaw("$periodSql as period, SUM(pancake_orders.final_amount) as total_sales")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->pluck('total_sales', 'period');

        $newCustomersByPeriod = DB::query()
            ->fromSub(
                $this->baseOrdersQuery(
                    $workspaceId,
                    $filter,
                    $endExclusive,
                    'idx_orders_workspace_customer_confirmed'
                )
                    ->selectRaw('
                        pancake_orders.customer_id,
                        MIN(pancake_orders.confirmed_at) as first_confirmed_at
                    ')
                    ->groupBy('pancake_orders.customer_id'),
                't'
            )
            ->where('t.first_confirmed_at', '>=', $startInclusive)
            ->selectRaw("$firstOrderPeriodSql as period, COUNT(*) as total_customers")
            ->groupByRaw($firstOrderPeriodSql)
            ->orderByRaw($firstOrderPeriodSql)
            ->pluck('total_customers', 'period');

        $periods = $this->generatePeriods($dateRange, $group);

        $runningSales = $baselineSales;
        $runningCustomers = $baselineCustomers;

        return collect($periods)->map(function ($period) use (
            &$runningSales,
            &$runningCustomers,
            $salesByPeriod,
            $newCustomersByPeriod
        ) {
            $runningSales += (float) ($salesByPeriod[$period] ?? 0);
            $runningCustomers += (int) ($newCustomersByPeriod[$period] ?? 0);

            return (object) [
                'period' => $period,
                'value' => round(
                    $runningCustomers > 0
                        ? $runningSales / $runningCustomers
                        : 0,
                    2
                ),
            ];
        });
    }

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        $endExclusive = \Carbon\Carbon::parse($dateRange['end_date'])
            ->addDay()
            ->startOfDay()
            ->toDateTimeString();

        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(! empty($filter['shop_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            })
            ->selectRaw('
            pages.id as page_id,
            pages.name as page_name,
            ROUND(
                COALESCE(
                    SUM(pancake_orders.final_amount) / NULLIF(COUNT(DISTINCT pancake_orders.customer_id), 0),
                    0
                ),
                2
            ) as value
        ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseOrdersQuery(
        int $workspaceId,
        array $filter,
        string $endExclusive,
        ?string $forceIndex = null
    ): Builder {
        $table = 'pancake_orders';

        if ($forceIndex) {
            $table .= " FORCE INDEX ({$forceIndex})";
        }

        return DB::table(DB::raw($table))
            ->when($this->needsPagesJoin($filter), function (Builder $query) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');
            })
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(! empty($filter['shop_ids']), function (Builder $query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            });
    }

    private function baseOrdersBetweenQuery(
        int $workspaceId,
        array $filter,
        ?string $startInclusive,
        string $endExclusive,
        ?string $forceIndex = null
    ): Builder {
        return $this->baseOrdersQuery($workspaceId, $filter, $endExclusive, $forceIndex)
            ->when($startInclusive, function (Builder $query) use ($startInclusive) {
                $query->where('pancake_orders.confirmed_at', '>=', $startInclusive);
            });
    }

    private function periodSql(string $column, string $group): string
    {
        return match ($group) {
            'weekly' => "DATE_FORMAT($column, '%x-W%v')",
            'monthly' => "DATE_FORMAT($column, '%Y-%m')",
            default => "DATE($column)",
        };
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start = Carbon::parse($dateRange['start_date']);
        $end = Carbon::parse($dateRange['end_date']);

        if ($group === 'weekly') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $last = $end->copy()->startOfWeek(Carbon::MONDAY);

            $periods = [];
            while ($cursor <= $last) {
                $periods[] = $cursor->format('o-\WW');
                $cursor->addWeek();
            }

            return $periods;
        }

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();

            $periods = [];
            while ($cursor <= $last) {
                $periods[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            return $periods;
        }

        return collect(CarbonPeriod::create(
            $start->copy()->startOfDay(),
            '1 day',
            $end->copy()->startOfDay()
        ))->map(fn (Carbon $date) => $date->format('Y-m-d'))->all();
    }

    private function needsPagesJoin(array $filter): bool
    {
        return ! empty($filter['page_ids']) || ! empty($filter['shop_ids']);
    }
}
