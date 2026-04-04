<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RepeatCustomerOrderCount
{
    public function compute(int $workspaceId, array $dateRange, array $filter): int
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        return $this->countForWindow($workspaceId, $filter, $startAt, $endExclusive);
    }

    public function breakdown(int $workspaceId, array $dateRange, array $filter, string $group = 'daily'): Collection
    {
        $periods = $this->generatePeriods($dateRange, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value'  => $this->countForWindow(
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

        $repeatCustomers = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->joinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('pages.id', 'pages.name')
            ->selectRaw('pages.id as page_id, pages.name as page_name, COUNT(*) as value')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $repeatCustomers = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->joinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->whereNotNull('pages.shop_id')
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('shops.id', $shopIds))
            ->groupBy('shops.id', 'shops.name')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, COUNT(*) as value')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $startAt      = Carbon::parse($dateRange['start_date'])->startOfDay()->toDateTimeString();
        $endExclusive = Carbon::parse($dateRange['end_date'])->addDay()->startOfDay()->toDateTimeString();

        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $repeatCustomers = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');

        return DB::table('pancake_orders as po')
            ->join('pages', 'pages.id', '=', 'po.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->joinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'po.customer_id'))
            ->where('po.workspace_id', $workspaceId)
            ->where('po.confirmed_at', '>=', $startAt)
            ->where('po.confirmed_at', '<', $endExclusive)
            ->whereNotNull('po.customer_id')
            ->whereNotIn('po.status', [6, 7])
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('pages.id', $pageIds))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('pages.shop_id', $shopIds))
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as user_id, users.name as user_name, COUNT(*) as value')
            ->orderByDesc('value')
            ->get();
    }

    private function countForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): int
    {
        // Customers who have >= 2 orders in the workspace up to the end of the window
        $repeatCustomers = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('confirmed_at', '<', $endExclusive)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [6, 7])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->select('customer_id');

        // Count orders in the window from those repeat customers
        $query = DB::table('pancake_orders')
            ->joinSub($repeatCustomers, 'rc', fn ($j) => $j->on('rc.customer_id', '=', 'pancake_orders.customer_id'))
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '>=', $startAt)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7]);

        if ($this->needsPagesJoin($filter)) {
            $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('pages.id',
                    is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids'])
                ))
                ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('pages.shop_id',
                    is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids'])
                ));
        }

        return $query->count();
    }

    private function generatePeriods(array $dateRange, string $group): array
    {
        $start  = Carbon::parse($dateRange['start_date'])->startOfDay();
        $end    = Carbon::parse($dateRange['end_date'])->startOfDay();
        $periods = [];

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor <= $end->copy()->startOfMonth()) {
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
            while ($cursor <= $end->copy()->startOfWeek(Carbon::MONDAY)) {
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
