<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class RetentionRateCohort
{
    abstract protected function days(): int;

    /**
     * Rolling cohort retention — always complete, independent of selected date range.
     *
     * Cohort  : customers whose first-ever order falls between (today - 2N days) and (today - N days).
     * Check   : did they order again within N days of that first order?
     * Result  : retained / new customers in cohort
     *
     * Because the cohort ends at (today - N days), every customer's N-day return
     * window has already fully elapsed, so the result is always a complete number.
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $days = $this->days();
        $cohortStart = Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString();
        $cohortEnd = Carbon::now()->subDays($days)->startOfDay()->toDateTimeString();

        return $this->computeForWindow($workspaceId, $filter, $cohortStart, $cohortEnd);
    }

    /**
     * Period-by-period breakdown — only includes periods whose N-day return
     * window has fully elapsed (incomplete periods are silently dropped).
     */
    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $days = $this->days();
        $periods = $this->generatePeriods($dateRange, $group);
        $now = Carbon::now();

        return collect($periods)
            ->filter(fn ($p) => Carbon::parse($p['end_exclusive'])->addDays($days)->lte($now))
            ->map(fn ($p) => (object) [
                'period' => $p['label'],
                'value' => $this->computeForWindow($workspaceId, $filter, $p['start'], $p['end_exclusive']),
            ])
            ->values();
    }

    /**
     * Retained new customers per page, using the same rolling cohort window.
     */
    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        $days = $this->days();
        $cohortStart = Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString();
        $cohortEnd = Carbon::now()->subDays($days)->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $newCustomers = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->where('po.workspace_id', $workspaceId)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('po.customer_id', 'pages.id', 'pages.name')
            ->havingRaw('MIN(po.confirmed_at) >= ? AND MIN(po.confirmed_at) < ?', [$cohortStart, $cohortEnd])
            ->selectRaw('po.customer_id, pages.id as page_id, pages.name as page_name, MIN(po.confirmed_at) as first_order_at');

        $retained = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->join('pancake_orders as po2', 'po2.customer_id', '=', 'nc.customer_id')
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->whereColumn('po2.confirmed_at', '>', 'nc.first_order_at')
            ->whereRaw("po2.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY)")
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            ->groupBy('nc.customer_id', 'nc.page_id', 'nc.page_name')
            ->selectRaw('nc.customer_id, nc.page_id, nc.page_name');

        return DB::query()
            ->fromSub($retained, 'r')
            ->groupBy('r.page_id', 'r.page_name')
            ->selectRaw('r.page_id, r.page_name, COUNT(DISTINCT r.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * Retained new customers per shop, using the same rolling cohort window.
     */
    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $days = $this->days();
        $cohortStart = Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString();
        $cohortEnd = Carbon::now()->subDays($days)->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $newCustomers = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->where('po.workspace_id', $workspaceId)
            ->whereNotNull('po.customer_id')
            ->whereNotNull('pages.shop_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('shops.id', $shopIds))
            ->groupBy('po.customer_id', 'shops.id', 'shops.name')
            ->havingRaw('MIN(po.confirmed_at) >= ? AND MIN(po.confirmed_at) < ?', [$cohortStart, $cohortEnd])
            ->selectRaw('po.customer_id, shops.id as shop_id, shops.name as shop_name, MIN(po.confirmed_at) as first_order_at');

        $retained = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->join('pancake_orders as po2', 'po2.customer_id', '=', 'nc.customer_id')
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->join('shops as s2', 's2.id', '=', 'p2.shop_id')
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->whereColumn('po2.confirmed_at', '>', 'nc.first_order_at')
            ->whereRaw("po2.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY)")
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('s2.id', $shopIds))
            ->groupBy('nc.customer_id', 'nc.shop_id', 'nc.shop_name')
            ->selectRaw('nc.customer_id, nc.shop_id, nc.shop_name');

        return DB::query()
            ->fromSub($retained, 'r')
            ->groupBy('r.shop_id', 'r.shop_name')
            ->selectRaw('r.shop_id, r.shop_name, COUNT(DISTINCT r.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * Retained new customers per user, using the same rolling cohort window.
     */
    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $days = $this->days();
        $cohortStart = Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString();
        $cohortEnd = Carbon::now()->subDays($days)->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $newCustomers = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->where('po.workspace_id', $workspaceId)
            ->whereNotNull('po.customer_id')
            ->whereNotNull('pages.owner_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('po.customer_id', 'users.id', 'users.name')
            ->havingRaw('MIN(po.confirmed_at) >= ? AND MIN(po.confirmed_at) < ?', [$cohortStart, $cohortEnd])
            ->selectRaw('po.customer_id, users.id as user_id, users.name as user_name, MIN(po.confirmed_at) as first_order_at');

        $retained = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->join('pancake_orders as po2', 'po2.customer_id', '=', 'nc.customer_id')
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->whereColumn('po2.confirmed_at', '>', 'nc.first_order_at')
            ->whereRaw("po2.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY)")
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            ->groupBy('nc.customer_id', 'nc.user_id', 'nc.user_name')
            ->selectRaw('nc.customer_id, nc.user_id, nc.user_name');

        return DB::query()
            ->fromSub($retained, 'r')
            ->groupBy('r.user_id', 'r.user_name')
            ->selectRaw('r.user_id, r.user_name, COUNT(DISTINCT r.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    private function computeForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): float
    {
        $days = $this->days();
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $newCustomers = DB::table('pancake_orders as po')
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) => $q->join('pages', 'pages.id', '=', 'po.page_id')
                ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            )
            ->where('po.workspace_id', $workspaceId)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->groupBy('po.customer_id')
            ->havingRaw('MIN(po.confirmed_at) >= ? AND MIN(po.confirmed_at) < ?', [$startAt, $endExclusive])
            ->selectRaw('po.customer_id, MIN(po.confirmed_at) as first_order_at');

        $newCount = (int) DB::query()
            ->fromSub($newCustomers, 'nc')
            ->selectRaw('COUNT(*) as total')
            ->value('total');

        if ($newCount === 0) {
            return 0.0;
        }

        $retained = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->join('pancake_orders as po2', 'po2.customer_id', '=', 'nc.customer_id')
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) => $q->join('pages as p2', 'p2.id', '=', 'po2.page_id')
                ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
                ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            )
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->whereColumn('po2.confirmed_at', '>', 'nc.first_order_at')
            ->whereRaw("po2.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY)")
            ->groupBy('nc.customer_id')
            ->selectRaw('nc.customer_id');

        $retainedCount = (int) DB::query()
            ->fromSub($retained, 'r')
            ->selectRaw('COUNT(DISTINCT r.customer_id) as total')
            ->value('total');

        return round($retainedCount / $newCount, 4);
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
                $periods[] = [
                    'label' => $cursor->format('Y-m'),
                    'start' => $cursor->copy()->startOfMonth()->toDateTimeString(),
                    'end_exclusive' => $cursor->copy()->addMonth()->startOfMonth()->toDateTimeString(),
                ];
                $cursor->addMonth();
            }

            return $periods;
        }

        if ($group === 'weekly') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $last = $end->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor <= $last) {
                $periods[] = [
                    'label' => $cursor->format('o-\WW'),
                    'start' => $cursor->copy()->toDateTimeString(),
                    'end_exclusive' => $cursor->copy()->addWeek()->toDateTimeString(),
                ];
                $cursor->addWeek();
            }

            return $periods;
        }

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periods[] = [
                'label' => $cursor->format('Y-m-d'),
                'start' => $cursor->copy()->startOfDay()->toDateTimeString(),
                'end_exclusive' => $cursor->copy()->addDay()->startOfDay()->toDateTimeString(),
            ];
            $cursor->addDay();
        }

        return $periods;
    }

    private function resolveIds(mixed $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return array_map('intval', is_array($ids) ? $ids : explode(',', $ids));
    }
}
