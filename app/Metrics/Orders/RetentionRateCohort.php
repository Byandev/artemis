<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class RetentionRateCohort
{
    abstract protected function days(): int;

    /**
     * Ratio of customers who ordered in the window and placed 2+ orders
     * within N days before their latest order, divided by unique customers in window.
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $uniqueCustomers = (int) DB::table('pancake_orders as po')
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) =>
                $q->join('pages', 'pages.id', '=', 'po.page_id')
                  ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                  ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            )
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->selectRaw('COUNT(DISTINCT po.customer_id) as total')
            ->value('total');

        if ($uniqueCustomers === 0) {
            return 0.0;
        }

        $qualified = $this->countForWindow($workspaceId, $filter, $startAt, $endExclusive);

        return round($qualified / $uniqueCustomers, 4);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $periods = $this->generatePeriods($dateRange, $group);
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter, $pageIds, $shopIds) {
            $uniqueCustomers = (int) DB::table('pancake_orders as po')
                ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) =>
                    $q->join('pages', 'pages.id', '=', 'po.page_id')
                      ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                      ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
                )
                ->where('po.workspace_id', $workspaceId)
                ->where('po.confirmed_at', '>=', $period['start'])
                ->where('po.confirmed_at', '<', $period['end_exclusive'])
                ->whereNotNull('po.customer_id')
                ->whereNotIn('po.status', [6, 7])
                ->selectRaw('COUNT(DISTINCT po.customer_id) as total')
                ->value('total');

            $qualified = $uniqueCustomers > 0
                ? $this->countForWindow($workspaceId, $filter, $period['start'], $period['end_exclusive'])
                : 0;

            return (object) [
                'period' => $period['label'],
                'value'  => $uniqueCustomers > 0 ? round($qualified / $uniqueCustomers, 4) : 0.0,
            ];
        });
    }

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();
        $days         = $this->days();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        // Latest order per customer in window, per page
        $customerLatest = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('po.customer_id', 'pages.id', 'pages.name')
            ->selectRaw('po.customer_id, pages.id as page_id, pages.name as page_name, MAX(po.confirmed_at) as latest_order_at');

        // Count orders per customer within N days before their latest order, on same page/shop
        $qualified = DB::query()
            ->fromSub($customerLatest, 'cl')
            ->join('pancake_orders as po2', function ($join) {
                $join->on('po2.customer_id', '=', 'cl.customer_id');
            })
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->whereColumn('po2.confirmed_at', '<=', 'cl.latest_order_at')
            ->whereRaw("po2.confirmed_at >= DATE_SUB(cl.latest_order_at, INTERVAL {$days} DAY)")
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            ->groupBy('cl.customer_id', 'cl.page_id', 'cl.page_name')
            ->havingRaw('COUNT(po2.id) >= 2')
            ->selectRaw('cl.customer_id, cl.page_id, cl.page_name');

        return DB::query()
            ->fromSub($qualified, 'q')
            ->groupBy('q.page_id', 'q.page_name')
            ->selectRaw('q.page_id, q.page_name, COUNT(DISTINCT q.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();
        $days         = $this->days();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $customerLatest = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->whereNotNull('pages.shop_id')
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('shops.id', $shopIds))
            ->groupBy('po.customer_id', 'shops.id', 'shops.name')
            ->selectRaw('po.customer_id, shops.id as shop_id, shops.name as shop_name, MAX(po.confirmed_at) as latest_order_at');

        $qualified = DB::query()
            ->fromSub($customerLatest, 'cl')
            ->join('pancake_orders as po2', fn ($j) => $j->on('po2.customer_id', '=', 'cl.customer_id'))
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->join('shops as s2', 's2.id', '=', 'p2.shop_id')
            ->whereColumn('po2.confirmed_at', '<=', 'cl.latest_order_at')
            ->whereRaw("po2.confirmed_at >= DATE_SUB(cl.latest_order_at, INTERVAL {$days} DAY)")
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('s2.id', $shopIds))
            ->groupBy('cl.customer_id', 'cl.shop_id', 'cl.shop_name')
            ->havingRaw('COUNT(po2.id) >= 2')
            ->selectRaw('cl.customer_id, cl.shop_id, cl.shop_name');

        return DB::query()
            ->fromSub($qualified, 'q')
            ->groupBy('q.shop_id', 'q.shop_name')
            ->selectRaw('q.shop_id, q.shop_name, COUNT(DISTINCT q.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();
        $days         = $this->days();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $customerLatest = DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotNull('pages.owner_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('po.customer_id', 'users.id', 'users.name')
            ->selectRaw('po.customer_id, users.id as user_id, users.name as user_name, MAX(po.confirmed_at) as latest_order_at');

        $qualified = DB::query()
            ->fromSub($customerLatest, 'cl')
            ->join('pancake_orders as po2', fn ($j) => $j->on('po2.customer_id', '=', 'cl.customer_id'))
            ->join('pages as p2', 'p2.id', '=', 'po2.page_id')
            ->whereColumn('po2.confirmed_at', '<=', 'cl.latest_order_at')
            ->whereRaw("po2.confirmed_at >= DATE_SUB(cl.latest_order_at, INTERVAL {$days} DAY)")
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            ->groupBy('cl.customer_id', 'cl.user_id', 'cl.user_name')
            ->havingRaw('COUNT(po2.id) >= 2')
            ->selectRaw('cl.customer_id, cl.user_id, cl.user_name');

        return DB::query()
            ->fromSub($qualified, 'q')
            ->groupBy('q.user_id', 'q.user_name')
            ->selectRaw('q.user_id, q.user_name, COUNT(DISTINCT q.customer_id) as value')
            ->orderByDesc('value')
            ->get();
    }

    private function countForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): int
    {
        $days    = $this->days();
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        // Latest order per customer in the window (filtered by page/shop)
        $customerLatest = DB::table('pancake_orders as po')
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) =>
                $q->join('pages', 'pages.id', '=', 'po.page_id')
                  ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                  ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            )
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->groupBy('po.customer_id')
            ->selectRaw('po.customer_id, MAX(po.confirmed_at) as latest_order_at');

        // Count orders within N days before latest order (same page/shop filter)
        $qualified = DB::query()
            ->fromSub($customerLatest, 'cl')
            ->join('pancake_orders as po2', fn ($j) => $j->on('po2.customer_id', '=', 'cl.customer_id'))
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) =>
                $q->join('pages as p2', 'p2.id', '=', 'po2.page_id')
                  ->when(! empty($pageIds), fn ($q) => $q->whereIn('p2.id', $pageIds))
                  ->when(! empty($shopIds), fn ($q) => $q->whereIn('p2.shop_id', $shopIds))
            )
            ->whereColumn('po2.confirmed_at', '<=', 'cl.latest_order_at')
            ->whereRaw("po2.confirmed_at >= DATE_SUB(cl.latest_order_at, INTERVAL {$days} DAY)")
            ->where('po2.workspace_id', $workspaceId)
            ->whereNotIn('po2.status', [6, 7])
            ->groupBy('cl.customer_id')
            ->havingRaw('COUNT(po2.id) >= 2')
            ->selectRaw('cl.customer_id');

        return (int) DB::query()
            ->fromSub($qualified, 'q')
            ->selectRaw('COUNT(DISTINCT q.customer_id) as total')
            ->value('total');
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start   = Carbon::parse($dateRange['start_date'])->startOfDay();
        $end     = Carbon::parse($dateRange['end_date'])->startOfDay();
        $periods = [];

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            $last   = $end->copy()->startOfMonth();
            while ($cursor <= $last) {
                $periods[] = [
                    'label'         => $cursor->format('Y-m'),
                    'start'         => $cursor->copy()->startOfMonth()->toDateTimeString(),
                    'end_exclusive' => $cursor->copy()->addMonth()->startOfMonth()->toDateTimeString(),
                ];
                $cursor->addMonth();
            }
            return $periods;
        }

        if ($group === 'weekly') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $last   = $end->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor <= $last) {
                $periods[] = [
                    'label'         => $cursor->format('o-\WW'),
                    'start'         => $cursor->copy()->toDateTimeString(),
                    'end_exclusive' => $cursor->copy()->addWeek()->toDateTimeString(),
                ];
                $cursor->addWeek();
            }
            return $periods;
        }

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periods[] = [
                'label'         => $cursor->format('Y-m-d'),
                'start'         => $cursor->copy()->startOfDay()->toDateTimeString(),
                'end_exclusive' => $cursor->copy()->addDay()->startOfDay()->toDateTimeString(),
            ];
            $cursor->addDay();
        }

        return $periods;
    }

    private function resolveIds(mixed $ids): array
    {
        if (empty($ids)) return [];
        return array_map('intval', is_array($ids) ? $ids : explode(',', $ids));
    }
}
