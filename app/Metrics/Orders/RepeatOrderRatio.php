<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RepeatOrderRatio
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

        return $this->computeRatioForWindow($workspaceId, $filter, $startAt, $endExclusive);
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
        $pages = DB::table('pages')
            ->where('workspace_id', $workspaceId)
            ->when(!empty($filter['page_ids']), function ($query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('id', $pageIds);
            })
            ->when(!empty($filter['shop_ids']), function ($query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('shop_id', $shopIds);
            })
            ->select('id', 'name')
            ->get();

        return $pages->map(function ($page) use ($workspaceId, $dateRange, $filter) {
            $pageFilter = $filter;
            $pageFilter['page_ids'] = [(int) $page->id];

            return (object) [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'value' => $this->compute($workspaceId, $dateRange, $pageFilter),
            ];
        })->sortByDesc('value')->values();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $shops = DB::table('shops')
            ->join('pages', 'pages.shop_id', '=', 'shops.id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(!empty($filter['shop_ids']), function ($query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('shops.id', $shopIds);
            })
            ->when(!empty($filter['page_ids']), function ($query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->select('shops.id', 'shops.name')
            ->distinct()
            ->get();

        return $shops->map(function ($shop) use ($workspaceId, $dateRange, $filter) {
            $shopFilter = $filter;
            $shopFilter['shop_ids'] = [(int) $shop->id];

            return (object) [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'value' => $this->compute($workspaceId, $dateRange, $shopFilter),
            ];
        })->sortByDesc('value')->values();
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
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(!empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(!empty($filter['shop_ids']), function (Builder $query) use ($filter) {
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
        $users = DB::table('users')
            ->join('pages', 'pages.owner_id', '=', 'users.id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(!empty($filter['shop_ids']), function ($query) use ($filter) {
                $shopIds = is_array($filter['shop_ids'])
                    ? $filter['shop_ids']
                    : explode(',', $filter['shop_ids']);

                $query->whereIn('pages.shop_id', $shopIds);
            })
            ->when(!empty($filter['page_ids']), function ($query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->select('users.id', 'users.name')
            ->distinct()
            ->get();

        return $users->map(function ($user) use ($workspaceId, $dateRange, $filter) {
            $userFilter = $filter;
            $userFilter['page_ids'] = DB::table('pages')
                ->where('workspace_id', $workspaceId)
                ->where('owner_id', $user->id)
                ->when(!empty($filter['shop_ids']), function ($query) use ($filter) {
                    $shopIds = is_array($filter['shop_ids'])
                        ? $filter['shop_ids']
                        : explode(',', $filter['shop_ids']);

                    $query->whereIn('shop_id', $shopIds);
                })
                ->when(!empty($filter['page_ids']), function ($query) use ($filter) {
                    $pageIds = is_array($filter['page_ids'])
                        ? $filter['page_ids']
                        : explode(',', $filter['page_ids']);

                    $query->whereIn('id', $pageIds);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return (object) [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'value' => !empty($userFilter['page_ids'])
                    ? $this->compute($workspaceId, $dateRange, $userFilter)
                    : 0,
            ];
        })->sortByDesc('value')->values();
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
            ->when(!empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(!empty($filter['shop_ids']), function (Builder $query) use ($filter) {
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
            ->when(!empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $pageIds = is_array($filter['page_ids'])
                    ? $filter['page_ids']
                    : explode(',', $filter['page_ids']);

                $query->whereIn('pages.id', $pageIds);
            })
            ->when(!empty($filter['shop_ids']), function (Builder $query) use ($filter) {
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
        return !empty($filter['page_ids']) || !empty($filter['shop_ids']);
    }
}
