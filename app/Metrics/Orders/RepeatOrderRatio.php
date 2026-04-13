<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RepeatOrderRatio
{
    /**
     * Orders placed by repeat customers / total orders in window.
     *
     * @return float ratio 0..1
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return $this->computeForWindow($workspaceId, $filter, $startAt, $endExclusive);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily'): Collection
    {
        $periods = $this->generatePeriods($dateRange, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value' => $this->computeForWindow(
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

        $repeatCustomers = $this->buildRepeatSubquery($workspaceId, $endExclusive);

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->leftJoinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('pages.id', 'pages.name')
            ->selectRaw('
                pages.id   AS page_id,
                pages.name AS page_name,
                ROUND(
                    COUNT(CASE WHEN rc.customer_id IS NOT NULL THEN 1 END) * 1.0
                    / NULLIF(COUNT(po.id), 0),
                4) AS value
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

        $repeatCustomers = $this->buildRepeatSubquery($workspaceId, $endExclusive);

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->leftJoinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
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
                shops.id   AS shop_id,
                shops.name AS shop_name,
                ROUND(
                    COUNT(CASE WHEN rc.customer_id IS NOT NULL THEN 1 END) * 1.0
                    / NULLIF(COUNT(po.id), 0),
                4) AS value
            ')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $repeatCustomers = $this->buildRepeatSubquery($workspaceId, $endExclusive);

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->leftJoinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotNull('pages.owner_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('users.id', 'users.name')
            ->selectRaw('
                users.id   AS user_id,
                users.name AS user_name,
                ROUND(
                    COUNT(CASE WHEN rc.customer_id IS NOT NULL THEN 1 END) * 1.0
                    / NULLIF(COUNT(po.id), 0),
                4) AS value
            ')
            ->orderByDesc('value')
            ->get();
    }

    private function computeForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): float
    {
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $repeatCustomers = $this->buildRepeatSubquery($workspaceId, $endExclusive);

        $row = DB::table('pancake_orders as po')
            ->when(! empty($pageIds) || ! empty($shopIds), fn ($q) => $q->join('pages', 'pages.id', '=', 'po.page_id')
                ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
                ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            )
            ->leftJoinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->selectRaw('
                COUNT(po.id)                                             AS total_orders,
                COUNT(CASE WHEN rc.customer_id IS NOT NULL THEN 1 END)  AS repeat_orders
            ')
            ->first();

        if (! $row || $row->total_orders == 0) {
            return 0.0;
        }

        return round($row->repeat_orders / $row->total_orders, 4);
    }

    /**
     * Customers with 2+ total orders in the workspace up to (but not including) $endExclusive.
     */
    private function buildRepeatSubquery(int $workspaceId, string $endExclusive)
    {
        return DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');
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
