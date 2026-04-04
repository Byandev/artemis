<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class RetentionRateCohort
{
    abstract protected function days(): int;

    /**
     * % of new-customer cohort that returned within N days.
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return $this->computeForWindow($workspaceId, $filter, $startAt, $endExclusive);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily')
    {
        $periods = $this->generatePeriods($dateRange, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value'  => $this->computeForWindow(
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
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);
        $days    = $this->days();

        $newCustomers = $this->buildNewCustomersQuery($workspaceId, $startAt, $endExclusive, $pageIds, $shopIds);

        $subsequentOrders = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->select('customer_id', 'confirmed_at');

        $perCustomer = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->leftJoinSub($subsequentOrders, 'po', function ($join) {
                $join->on('po.customer_id', '=', 'nc.customer_id')
                    ->whereColumn('po.confirmed_at', '>', 'nc.first_order_at');
            })
            ->join('pancake_orders as orig', 'orig.customer_id', '=', 'nc.customer_id')
            ->join('pages', 'pages.id', '=', 'orig.page_id')
            ->where('orig.workspace_id', $workspaceId)
            ->whereColumn('orig.confirmed_at', '=', 'nc.first_order_at')
            ->groupBy('pages.id', 'pages.name', 'nc.customer_id', 'nc.first_order_at')
            ->selectRaw("
                pages.id   as page_id,
                pages.name as page_name,
                MAX(CASE WHEN po.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as returned
            ");

        return DB::query()
            ->fromSub($perCustomer, 'pc')
            ->groupBy('pc.page_id', 'pc.page_name')
            ->selectRaw('
                pc.page_id,
                pc.page_name,
                ROUND(COALESCE(SUM(returned) * 1.0 / NULLIF(COUNT(*), 0), 0), 4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);
        $days    = $this->days();

        $newCustomers = $this->buildNewCustomersQuery($workspaceId, $startAt, $endExclusive, $pageIds, $shopIds);

        $subsequentOrders = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->select('customer_id', 'confirmed_at');

        $perCustomer = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->leftJoinSub($subsequentOrders, 'po', function ($join) {
                $join->on('po.customer_id', '=', 'nc.customer_id')
                    ->whereColumn('po.confirmed_at', '>', 'nc.first_order_at');
            })
            ->join('pancake_orders as orig', 'orig.customer_id', '=', 'nc.customer_id')
            ->join('pages', 'pages.id', '=', 'orig.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->where('orig.workspace_id', $workspaceId)
            ->whereColumn('orig.confirmed_at', '=', 'nc.first_order_at')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name', 'nc.customer_id', 'nc.first_order_at')
            ->selectRaw("
                shops.id   as shop_id,
                shops.name as shop_name,
                MAX(CASE WHEN po.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as returned
            ");

        return DB::query()
            ->fromSub($perCustomer, 'pc')
            ->groupBy('pc.shop_id', 'pc.shop_name')
            ->selectRaw('
                pc.shop_id,
                pc.shop_name,
                ROUND(COALESCE(SUM(returned) * 1.0 / NULLIF(COUNT(*), 0), 0), 4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);
        $days    = $this->days();

        $newCustomers = $this->buildNewCustomersQuery($workspaceId, $startAt, $endExclusive, $pageIds, $shopIds);

        $subsequentOrders = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->select('customer_id', 'confirmed_at');

        $perCustomer = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->leftJoinSub($subsequentOrders, 'po', function ($join) {
                $join->on('po.customer_id', '=', 'nc.customer_id')
                    ->whereColumn('po.confirmed_at', '>', 'nc.first_order_at');
            })
            ->join('pancake_orders as orig', 'orig.customer_id', '=', 'nc.customer_id')
            ->join('pages', 'pages.id', '=', 'orig.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->where('orig.workspace_id', $workspaceId)
            ->whereColumn('orig.confirmed_at', '=', 'nc.first_order_at')
            ->groupBy('users.id', 'users.name', 'nc.customer_id', 'nc.first_order_at')
            ->selectRaw("
                users.id   as user_id,
                users.name as user_name,
                MAX(CASE WHEN po.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as returned
            ");

        return DB::query()
            ->fromSub($perCustomer, 'pc')
            ->groupBy('pc.user_id', 'pc.user_name')
            ->selectRaw('
                pc.user_id,
                pc.user_name,
                ROUND(COALESCE(SUM(returned) * 1.0 / NULLIF(COUNT(*), 0), 0), 4) as value
            ')
            ->orderByDesc('value')
            ->get();
    }

    private function computeForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): float
    {
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);
        $days    = $this->days();

        $newCustomers = $this->buildNewCustomersQuery($workspaceId, $startAt, $endExclusive, $pageIds, $shopIds);

        $subsequentOrders = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->select('customer_id', 'confirmed_at');

        $retentionData = DB::query()
            ->fromSub($newCustomers, 'nc')
            ->leftJoinSub($subsequentOrders, 'po', function ($join) {
                $join->on('po.customer_id', '=', 'nc.customer_id')
                    ->whereColumn('po.confirmed_at', '>', 'nc.first_order_at');
            })
            ->groupBy('nc.customer_id', 'nc.first_order_at')
            ->selectRaw("
                nc.customer_id,
                MAX(CASE WHEN po.confirmed_at <= DATE_ADD(nc.first_order_at, INTERVAL {$days} DAY) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as returned
            ");

        $result = DB::query()
            ->fromSub($retentionData, 'ret')
            ->selectRaw('ROUND(COALESCE(SUM(returned) * 1.0 / NULLIF(COUNT(*), 0), 0), 4) as rate')
            ->first();

        return round((float) ($result->rate ?? 0), 4);
    }

    private function buildNewCustomersQuery(
        int $workspaceId,
        string $startAt,
        string $endExclusive,
        array $pageIds,
        array $shopIds
    ) {
        $needsPages = ! empty($pageIds) || ! empty($shopIds);

        $firstOrders = DB::table('pancake_orders')
            ->when($needsPages, fn ($q) => $q->join('pages', 'pages.id', '=', 'pancake_orders.page_id'))
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('pancake_orders.customer_id, MIN(pancake_orders.confirmed_at) as first_order_at');

        return DB::query()
            ->fromSub($firstOrders, 'fo')
            ->where('fo.first_order_at', '>=', $startAt)
            ->where('fo.first_order_at', '<', $endExclusive)
            ->select('fo.customer_id', 'fo.first_order_at');
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start  = Carbon::parse($dateRange['start_date'])->startOfDay();
        $end    = Carbon::parse($dateRange['end_date'])->startOfDay();
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
        if (empty($ids)) {
            return [];
        }

        return array_map('intval', is_array($ids) ? $ids : explode(',', $ids));
    }
}
