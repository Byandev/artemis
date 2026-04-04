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
        $pages = DB::table('pages')
            ->where('workspace_id', $workspaceId)
            ->when(! empty($filter['page_ids']), function ($q) use ($filter) {
                $q->whereIn('id', is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']));
            })
            ->when(! empty($filter['shop_ids']), function ($q) use ($filter) {
                $q->whereIn('shop_id', is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']));
            })
            ->select('id', 'name')
            ->get();

        return $pages->map(function ($page) use ($workspaceId, $dateRange, $filter) {
            $f = array_merge($filter, ['page_ids' => [(int) $page->id]]);

            return (object) [
                'page_id'   => $page->id,
                'page_name' => $page->name,
                'value'     => $this->compute($workspaceId, $dateRange, $f),
            ];
        })->sortByDesc('value')->values();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        $shops = DB::table('shops')
            ->join('pages', 'pages.shop_id', '=', 'shops.id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(! empty($filter['shop_ids']), function ($q) use ($filter) {
                $q->whereIn('shops.id', is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']));
            })
            ->when(! empty($filter['page_ids']), function ($q) use ($filter) {
                $q->whereIn('pages.id', is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']));
            })
            ->select('shops.id', 'shops.name')
            ->distinct()
            ->get();

        return $shops->map(function ($shop) use ($workspaceId, $dateRange, $filter) {
            $f = array_merge($filter, ['shop_ids' => [(int) $shop->id]]);

            return (object) [
                'shop_id'   => $shop->id,
                'shop_name' => $shop->name,
                'value'     => $this->compute($workspaceId, $dateRange, $f),
            ];
        })->sortByDesc('value')->values();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        $users = DB::table('users')
            ->join('pages', 'pages.owner_id', '=', 'users.id')
            ->where('pages.workspace_id', $workspaceId)
            ->when(! empty($filter['shop_ids']), function ($q) use ($filter) {
                $q->whereIn('pages.shop_id', is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']));
            })
            ->when(! empty($filter['page_ids']), function ($q) use ($filter) {
                $q->whereIn('pages.id', is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']));
            })
            ->select('users.id', 'users.name')
            ->distinct()
            ->get();

        return $users->map(function ($user) use ($workspaceId, $dateRange, $filter) {
            $pageIds = DB::table('pages')
                ->where('workspace_id', $workspaceId)
                ->where('owner_id', $user->id)
                ->when(! empty($filter['shop_ids']), function ($q) use ($filter) {
                    $q->whereIn('shop_id', is_array($filter['shop_ids']) ? $filter['shop_ids'] : explode(',', $filter['shop_ids']));
                })
                ->when(! empty($filter['page_ids']), function ($q) use ($filter) {
                    $q->whereIn('id', is_array($filter['page_ids']) ? $filter['page_ids'] : explode(',', $filter['page_ids']));
                })
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $f = array_merge($filter, ['page_ids' => $pageIds]);

            return (object) [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'value'     => ! empty($pageIds) ? $this->compute($workspaceId, $dateRange, $f) : 0,
            ];
        })->sortByDesc('value')->values();
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
}
