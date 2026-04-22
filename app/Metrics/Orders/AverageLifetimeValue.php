<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AverageLifetimeValue
{
    /**
     * Average lifetime value up to selected end date.
     * Formula: total confirmed sales up to end date / unique confirmed customers up to end date.
     *
     * Reads from workspace_customer_facts. page/shop filter falls back to live.
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        if ($this->hasEntityFilter($filter)) {
            return $this->computeLive($workspaceId, $dateRange, $filter);
        }

        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $row = DB::table('workspace_customer_facts')
            ->where('workspace_id', $workspaceId)
            ->where('first_confirmed_at', '<', $endExclusive)
            ->selectRaw('
                COALESCE(SUM(total_confirmed_spend) / NULLIF(COUNT(*), 0), 0) as avg_lifetime_value
            ')
            ->first();

        return round((float) ($row->avg_lifetime_value ?? 0), 2);
    }

    /**
     * Running-total breakdown — ALV as of each period's end.
     * Reads sales per period from activity_daily; new customers per period from customer_facts.
     * Falls back to live when page/shop filter is set.
     */
    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        if ($this->hasEntityFilter($filter)) {
            return $this->breakdownLive($workspaceId, $dateRange, $filter, $group);
        }

        $startInclusive = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $periodSql = $this->activityPeriodSql($group);
        $customerPeriodSql = $this->customerFactsPeriodSql($group);

        $baselineSales = (float) DB::table('workspace_customer_activity_daily')
            ->where('workspace_id', $workspaceId)
            ->where('date', '<', Carbon::parse($startInclusive)->toDateString())
            ->sum('confirmed_spend');

        $baselineCustomers = (int) DB::table('workspace_customer_facts')
            ->where('workspace_id', $workspaceId)
            ->where('first_confirmed_at', '<', $startInclusive)
            ->count();

        $salesByPeriod = DB::table('workspace_customer_activity_daily')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [
                Carbon::parse($startInclusive)->toDateString(),
                Carbon::parse($endExclusive)->subSecond()->toDateString(),
            ])
            ->selectRaw("$periodSql as period, SUM(confirmed_spend) as total_sales")
            ->groupByRaw($periodSql)
            ->pluck('total_sales', 'period');

        $newCustomersByPeriod = DB::table('workspace_customer_facts')
            ->where('workspace_id', $workspaceId)
            ->where('first_confirmed_at', '>=', $startInclusive)
            ->where('first_confirmed_at', '<', $endExclusive)
            ->selectRaw("$customerPeriodSql as period, COUNT(*) as total_customers")
            ->groupByRaw($customerPeriodSql)
            ->pluck('total_customers', 'period');

        $periods = $this->generatePeriods($dateRange, $group);

        $runningSales = $baselineSales;
        $runningCustomers = $baselineCustomers;

        return collect($periods)->map(function ($period) use (&$runningSales, &$runningCustomers, $salesByPeriod, $newCustomersByPeriod) {
            $runningSales += (float) ($salesByPeriod[$period] ?? 0);
            $runningCustomers += (int) ($newCustomersByPeriod[$period] ?? 0);

            return (object) [
                'period' => $period,
                'value' => round($runningCustomers > 0 ? $runningSales / $runningCustomers : 0, 2),
            ];
        });
    }

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return DB::table('workspace_customer_activity_daily as a')
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.date', '<', Carbon::parse($endExclusive)->toDateString())
            ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('pages.id', $this->parseIds($filter['page_ids'])))
            ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids'])))
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                ROUND(COALESCE(SUM(a.confirmed_spend) / NULLIF(COUNT(DISTINCT a.customer_id), 0), 0), 2) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return DB::table('workspace_customer_activity_daily as a')
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.date', '<', Carbon::parse($endExclusive)->toDateString())
            ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('pages.id', $this->parseIds($filter['page_ids'])))
            ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids'])))
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                ROUND(COALESCE(SUM(a.confirmed_spend) / NULLIF(COUNT(DISTINCT a.customer_id), 0), 0), 2) as value
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return DB::table('workspace_customer_activity_daily as a')
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.date', '<', Carbon::parse($endExclusive)->toDateString())
            ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('pages.id', $this->parseIds($filter['page_ids'])))
            ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids'])))
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                ROUND(COALESCE(SUM(a.confirmed_spend) / NULLIF(COUNT(DISTINCT a.customer_id), 0), 0), 2) as value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function hasEntityFilter(array $filter): bool
    {
        return ! empty($filter['page_ids']) || ! empty($filter['shop_ids']);
    }

    private function activityPeriodSql(string $group): string
    {
        return match ($group) {
            'weekly' => "DATE_FORMAT(date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'DATE(date)',
        };
    }

    private function customerFactsPeriodSql(string $group): string
    {
        return match ($group) {
            'weekly' => "DATE_FORMAT(first_confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(first_confirmed_at, '%Y-%m')",
            default => 'DATE(first_confirmed_at)',
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

        return collect(CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->all();
    }

    private function parseIds(array|string $value): array
    {
        return is_array($value) ? $value : array_filter(explode(',', $value));
    }

    // ---- live fallbacks (when page/shop filter is set) ----

    private function computeLive(int $workspaceId, array $dateRange, array $filter): float
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $salesQuery = DB::query()->fromSub(
            $this->baseOrdersQuery($workspaceId, $filter, $endExclusive)
                ->selectRaw('SUM(pancake_orders.final_amount) as total_sales'),
            's'
        );

        $customersQuery = DB::query()->fromSub(
            $this->baseOrdersQuery($workspaceId, $filter, $endExclusive)
                ->select('pancake_orders.customer_id')
                ->groupBy('pancake_orders.customer_id'),
            'x'
        )->selectRaw('COUNT(*) as total_customers');

        $row = $salesQuery
            ->crossJoinSub($customersQuery, 'c')
            ->selectRaw('COALESCE(s.total_sales / NULLIF(c.total_customers, 0), 0) as avg_lifetime_value')
            ->first();

        return round((float) ($row->avg_lifetime_value ?? 0), 2);
    }

    private function breakdownLive(int $workspaceId, array $dateRange, array $filter, string $group)
    {
        $startInclusive = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(pancake_orders.confirmed_at, '%Y-%m')",
            default => 'DATE(pancake_orders.confirmed_at)',
        };
        $firstOrderPeriodSql = match ($group) {
            'weekly' => "DATE_FORMAT(t.first_confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(t.first_confirmed_at, '%Y-%m')",
            default => 'DATE(t.first_confirmed_at)',
        };

        $baselineSales = (float) $this->baseOrdersBetweenQuery($workspaceId, $filter, null, $startInclusive)
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount), 0) as total_sales')
            ->value('total_sales');

        $baselineCustomers = (int) DB::query()->fromSub(
            $this->baseOrdersBetweenQuery($workspaceId, $filter, null, $startInclusive)
                ->select('pancake_orders.customer_id')
                ->groupBy('pancake_orders.customer_id'),
            'x'
        )->selectRaw('COUNT(*) as total_customers')->value('total_customers');

        $salesByPeriod = $this->baseOrdersBetweenQuery($workspaceId, $filter, $startInclusive, $endExclusive)
            ->selectRaw("$periodSql as period, SUM(pancake_orders.final_amount) as total_sales")
            ->groupByRaw($periodSql)
            ->pluck('total_sales', 'period');

        $newCustomersByPeriod = DB::query()->fromSub(
            $this->baseOrdersQuery($workspaceId, $filter, $endExclusive)
                ->selectRaw('pancake_orders.customer_id, MIN(pancake_orders.confirmed_at) as first_confirmed_at')
                ->groupBy('pancake_orders.customer_id'),
            't'
        )
            ->where('t.first_confirmed_at', '>=', $startInclusive)
            ->selectRaw("$firstOrderPeriodSql as period, COUNT(*) as total_customers")
            ->groupByRaw($firstOrderPeriodSql)
            ->pluck('total_customers', 'period');

        $periods = $this->generatePeriods($dateRange, $group);
        $runningSales = $baselineSales;
        $runningCustomers = $baselineCustomers;

        return collect($periods)->map(function ($period) use (&$runningSales, &$runningCustomers, $salesByPeriod, $newCustomersByPeriod) {
            $runningSales += (float) ($salesByPeriod[$period] ?? 0);
            $runningCustomers += (int) ($newCustomersByPeriod[$period] ?? 0);

            return (object) [
                'period' => $period,
                'value' => round($runningCustomers > 0 ? $runningSales / $runningCustomers : 0, 2),
            ];
        });
    }

    private function baseOrdersQuery(int $workspaceId, array $filter, string $endExclusive): Builder
    {
        return DB::table('pancake_orders')
            ->when($this->hasEntityFilter($filter), fn ($q) => $q->join('pages', 'pages.id', '=', 'pancake_orders.page_id'))
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('pages.id', $this->parseIds($filter['page_ids'])))
            ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids'])));
    }

    private function baseOrdersBetweenQuery(int $workspaceId, array $filter, ?string $startInclusive, string $endExclusive): Builder
    {
        return $this->baseOrdersQuery($workspaceId, $filter, $endExclusive)
            ->when($startInclusive, fn ($q) => $q->where('pancake_orders.confirmed_at', '>=', $startInclusive));
    }
}
