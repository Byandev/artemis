<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RepeatCustomerRatio
{
    /**
     * Overall repeat-customer rate.
     *
     * @return float ratio 0..1
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $base = DB::table('pancake_orders')
            ->when(! empty($pageIds) || ! empty($shopIds), function ($q) use ($pageIds, $shopIds) {
                $q->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                    ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                    ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds));
            })
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '>=', $startAt)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7]);

        $uniqueCustomers = (int) (clone $base)->selectRaw('COUNT(DISTINCT pancake_orders.customer_id) as total')->value('total');

        if ($uniqueCustomers === 0) {
            return 0.0;
        }

        $repeatCustomers = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');

        $repeatCount = (int) (clone $base)
            ->joinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'pancake_orders.customer_id'))
            ->selectRaw('COUNT(DISTINCT pancake_orders.customer_id) as total')
            ->value('total');

        return round($repeatCount / $uniqueCustomers, 4);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily'): Collection
    {
        $periods = $this->generatePeriods($dateRange, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value' => $this->computeRatioForWindow(
                    $workspaceId,
                    $filter,
                    $period['start'],
                    $period['end_exclusive']
                ),
            ];
        });
    }

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $customerTotals = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '>=', $startAt)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total_orders');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->joinSub($customerTotals, 'ct', fn ($j) => $j->on('ct.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('pages.id', 'pages.name')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                ROUND(
                    COALESCE(SUM(CASE WHEN ct.total_orders >= 2 THEN 1 ELSE 0 END) * 1.0
                    / NULLIF(COUNT(DISTINCT po.customer_id), 0), 0),
                4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $customerTotals = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '>=', $startAt)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total_orders');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->joinSub($customerTotals, 'ct', fn ($j) => $j->on('ct.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->whereNotNull('pages.shop_id')
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('shops.id', $shopIds))
            ->groupBy('shops.id', 'shops.name')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                ROUND(
                    COALESCE(SUM(CASE WHEN ct.total_orders >= 2 THEN 1 ELSE 0 END) * 1.0
                    / NULLIF(COUNT(DISTINCT po.customer_id), 0), 0),
                4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    private function computeRatioForWindow(
        int $workspaceId,
        array $filter,
        string $startAt,
        string $endExclusive
    ): float {
        $cohort = $this->buildCohortQuery($workspaceId, $filter, $startAt, $endExclusive);

        $ordersUpToEnd = DB::table('pancake_orders as po')
            ->when($this->needsPagesJoin($filter), function (Builder $query) {
                $query->join('pages', 'pages.id', '=', 'po.page_id');
            })
            ->joinSub(
                $this->buildCohortCustomerIdsQuery($workspaceId, $filter, $startAt, $endExclusive),
                'c2',
                function ($join) {
                    $join->on('c2.customer_id', '=', 'po.customer_id');
                }
            )
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($filter['shop_ids']), function (Builder $query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->groupBy('po.customer_id')
            ->selectRaw('po.customer_id as customer_key, COUNT(*) as orders_count');

        $row = DB::query()
            ->fromSub($cohort, 'c')
            ->leftJoinSub($ordersUpToEnd, 'o', function ($join) {
                $join->on('o.customer_key', '=', 'c.customer_key');
            })
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN COALESCE(o.orders_count, 0) >= 2 THEN 1 ELSE 0 END) * 1.0
                    / NULLIF(COUNT(*), 0),
                    0
                ) as repeat_ratio
            ')
            ->first();

        return round((float) ($row->repeat_ratio ?? 0), 4);
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $customerTotals = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '>=', $startAt)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total_orders');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->joinSub($customerTotals, 'ct', fn ($j) => $j->on('ct.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('users.id', 'users.name')
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                ROUND(
                    COALESCE(SUM(CASE WHEN ct.total_orders >= 2 THEN 1 ELSE 0 END) * 1.0
                    / NULLIF(COUNT(DISTINCT po.customer_id), 0), 0),
                4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    private function buildCohortQuery(
        int $workspaceId,
        array $filter,
        string $startAt,
        string $endExclusive
    ): Builder {
        return DB::table('pancake_orders')
            ->when($this->needsPagesJoin($filter), function (Builder $query) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');
            })
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '>=', $startAt)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($filter['shop_ids']), function (Builder $query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id as customer_key');
    }

    private function buildCohortCustomerIdsQuery(
        int $workspaceId,
        array $filter,
        string $startAt,
        string $endExclusive
    ): Builder {
        return DB::table('pancake_orders')
            ->when($this->needsPagesJoin($filter), function (Builder $query) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');
            })
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '>=', $startAt)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(! empty($filter['shop_ids']), function (Builder $query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->groupBy('pancake_orders.customer_id')
            ->select('pancake_orders.customer_id');
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start = Carbon::parse($dateRange['start_date'])->startOfDay();
        $end = Carbon::parse($dateRange['end_date'])->startOfDay();

        $periods = [];

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();

            while ($cursor <= $last) {
                $periodStart = $cursor->copy()->startOfMonth();
                $periodEndExclusive = $cursor->copy()->addMonth()->startOfMonth();

                $periods[] = [
                    'label' => $periodStart->format('Y-m'),
                    'start' => $periodStart->toDateTimeString(),
                    'end_exclusive' => $periodEndExclusive->toDateTimeString(),
                ];

                $cursor->addMonth();
            }

            return $periods;
        }

        if ($group === 'weekly') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $last = $end->copy()->startOfWeek(Carbon::MONDAY);

            while ($cursor <= $last) {
                $periodStart = $cursor->copy()->startOfWeek(Carbon::MONDAY);
                $periodEndExclusive = $cursor->copy()->addWeek()->startOfWeek(Carbon::MONDAY);

                $periods[] = [
                    'label' => $periodStart->format('o-\WW'),
                    'start' => $periodStart->toDateTimeString(),
                    'end_exclusive' => $periodEndExclusive->toDateTimeString(),
                ];

                $cursor->addWeek();
            }

            return $periods;
        }

        $cursor = $start->copy();

        while ($cursor <= $end) {
            $periodStart = $cursor->copy()->startOfDay();
            $periodEndExclusive = $cursor->copy()->addDay()->startOfDay();

            $periods[] = [
                'label' => $periodStart->format('Y-m-d'),
                'start' => $periodStart->toDateTimeString(),
                'end_exclusive' => $periodEndExclusive->toDateTimeString(),
            ];

            $cursor->addDay();
        }

        return $periods;
    }

    private function needsPagesJoin(array $filter): bool
    {
        return ! empty($filter['page_ids']) || ! empty($filter['shop_ids']);
    }

    private function resolveIds(mixed $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return array_map('intval', is_array($ids) ? $ids : explode(',', $ids));
    }
}
