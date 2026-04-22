<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RepeatCustomerRatio
{
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $row = $this->baseQuery($workspaceId, $dateRange, $filter)
            ->joinSub($this->customerTotalsSub($workspaceId, $dateRange, $filter), 'ct', 'ct.customer_id', '=', 'a.customer_id')
            ->selectRaw('
                ROUND(
                    COALESCE(
                        COUNT(DISTINCT CASE WHEN ct.total >= 2 THEN a.customer_id END) * 1.0
                        / NULLIF(COUNT(DISTINCT a.customer_id), 0),
                        0
                    ),
                    4
                ) as ratio
            ')
            ->first();

        return (float) ($row->ratio ?? 0);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily'): Collection
    {
        $periods = $this->generatePeriods($dateRange, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value' => $this->compute(
                    $workspaceId,
                    ['start_date' => $period['start_date'], 'end_date' => $period['end_date']],
                    $filter
                ),
            ];
        });
    }

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        return $this->baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->joinSub($this->customerTotalsSub($workspaceId, $dateRange, $filter), 'ct', 'ct.customer_id', '=', 'a.customer_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                ROUND(
                    COALESCE(
                        COUNT(DISTINCT CASE WHEN ct.total >= 2 THEN a.customer_id END) * 1.0
                        / NULLIF(COUNT(DISTINCT a.customer_id), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        return $this->baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->joinSub($this->customerTotalsSub($workspaceId, $dateRange, $filter), 'ct', 'ct.customer_id', '=', 'a.customer_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                ROUND(
                    COALESCE(
                        COUNT(DISTINCT CASE WHEN ct.total >= 2 THEN a.customer_id END) * 1.0
                        / NULLIF(COUNT(DISTINCT a.customer_id), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        return $this->baseQuery($workspaceId, $dateRange, $filter)
            ->join('pages', 'pages.id', '=', 'a.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->joinSub($this->customerTotalsSub($workspaceId, $dateRange, $filter), 'ct', 'ct.customer_id', '=', 'a.customer_id')
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                ROUND(
                    COALESCE(
                        COUNT(DISTINCT CASE WHEN ct.total >= 2 THEN a.customer_id END) * 1.0
                        / NULLIF(COUNT(DISTINCT a.customer_id), 0),
                        0
                    ),
                    4
                ) as value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $dateRange, array $filter)
    {
        [$pageIds, $shopIds] = $this->parseFilters($filter);

        return DB::table('workspace_customer_activity_daily as a')
            ->where('a.workspace_id', $workspaceId)
            ->whereBetween('a.date', [$dateRange['start_date'], $dateRange['end_date']])
            ->when($pageIds, fn ($q) => $q->whereIn('a.page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('a.page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            });
    }

    /**
     * Per-customer total order count within the window, summed across all pages.
     * Customers with total >= 2 are "repeat" for this window.
     */
    private function customerTotalsSub(int $workspaceId, array $dateRange, array $filter)
    {
        [$pageIds, $shopIds] = $this->parseFilters($filter);

        return DB::table('workspace_customer_activity_daily')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$dateRange['start_date'], $dateRange['end_date']])
            ->when($pageIds, fn ($q) => $q->whereIn('page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            })
            ->selectRaw('customer_id, SUM(confirmed_count) as total')
            ->groupBy('customer_id');
    }

    private function parseFilters(array $filter): array
    {
        $pageIds = ! empty($filter['page_ids'])
            ? (is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']))
            : null;
        $shopIds = ! empty($filter['shop_ids'])
            ? (is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']))
            : null;

        return [$pageIds, $shopIds];
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start = Carbon::parse($dateRange['start_date'])->startOfDay();
        $end = Carbon::parse($dateRange['end_date'])->startOfDay();
        $periods = [];

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor <= $end) {
                $pStart = $cursor->copy()->startOfMonth();
                $pEnd = $cursor->copy()->endOfMonth();
                $periods[] = [
                    'label' => $pStart->format('Y-m'),
                    'start_date' => $pStart->toDateString(),
                    'end_date' => $pEnd->toDateString(),
                ];
                $cursor->addMonth();
            }

            return $periods;
        }

        if ($group === 'weekly') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor <= $end) {
                $pStart = $cursor->copy();
                $pEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
                $periods[] = [
                    'label' => $pStart->format('o-\WW'),
                    'start_date' => $pStart->toDateString(),
                    'end_date' => $pEnd->toDateString(),
                ];
                $cursor->addWeek();
            }

            return $periods;
        }

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periods[] = [
                'label' => $cursor->format('Y-m-d'),
                'start_date' => $cursor->toDateString(),
                'end_date' => $cursor->toDateString(),
            ];
            $cursor->addDay();
        }

        return $periods;
    }
}
