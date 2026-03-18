<?php

namespace App\Metrics\Orders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TimeToFirstOrder
{
    /**
     * Avg time from customer.created_at -> customer's true first confirmed order (in HOURS)
     * Only includes customers whose first confirmed order falls within the selected range.
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        $firstOrderPerCustomer = $this->firstOrderPerCustomerQuery($workspaceId, $filter);

        $row = DB::query()
            ->fromSub($firstOrderPerCustomer, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                        0
                    ),
                    2
                ) as value
            ')
            ->first();

        return (float) ($row->value ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'daily' => 'DATE(t.first_confirmed_at)',
            'weekly' => "DATE_FORMAT(t.first_confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(t.first_confirmed_at, '%Y-%m')",
            default => 'DATE(t.first_confirmed_at)',
        };

        $firstOrderPerCustomer = $this->firstOrderPerCustomerQuery($workspaceId, $filter);

        return DB::query()
            ->fromSub($firstOrderPerCustomer, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw("
                $periodSql as period,
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                        0
                    ),
                    2
                ) as value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        $firstOrderPerCustomerPerPage = $this->firstOrderPerCustomerPerPageQuery($workspaceId, $filter);

        return DB::query()
            ->fromSub($firstOrderPerCustomerPerPage, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                t.page_id,
                t.page_name,
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                        0
                    ),
                    2
                ) as value
            ')
            ->groupBy('t.page_id', 't.page_name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        $firstOrderPerCustomerPerStore = $this->firstOrderPerCustomerPerStoreQuery($workspaceId, $filter);

        return DB::query()
            ->fromSub($firstOrderPerCustomerPerStore, 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                t.shop_id,
                t.shop_name,
                ROUND(
                    COALESCE(
                        AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)),
                        0
                    ),
                    2
                ) as value
            ')
            ->groupBy('t.shop_id', 't.shop_name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
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

        return $users->map(function ($user) use ($workspaceId, $date_range, $filter) {
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
                    ? $this->compute($workspaceId, $date_range, $userFilter)
                    : 0,
            ];
        })->sortByDesc('value')->values();
    }

    /**
     * True first confirmed order per customer
     */
    private function firstOrderPerCustomerQuery(int $workspaceId, array $filter): Builder
    {
        return DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(
                ! empty($filter['page_ids']) || ! empty($filter['shop_ids']),
                function ($query) use ($filter) {
                    $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                        ->when(! empty($filter['page_ids']), function ($query) use ($filter) {
                            $query->whereIn('pages.id', $this->parseIds($filter['page_ids']));
                        })
                        ->when(! empty($filter['shop_ids']), function ($query) use ($filter) {
                            $query->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids']));
                        });
                }
            )
            ->groupBy('pancake_orders.customer_id')
            ->selectRaw('
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at
            ');
    }

    /**
     * True first confirmed order per customer per page
     */
    private function firstOrderPerCustomerPerPageQuery(int $workspaceId, array $filter): Builder
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.id', $this->parseIds($filter['page_ids']));
            })
            ->when(! empty($filter['shop_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids']));
            })
            ->groupBy('pages.id', 'pages.name', 'pancake_orders.customer_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at
            ');
    }

    /**
     * True first confirmed order per customer per store
     */
    private function firstOrderPerCustomerPerStoreQuery(int $workspaceId, array $filter): Builder
    {
        return DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(! empty($filter['page_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.id', $this->parseIds($filter['page_ids']));
            })
            ->when(! empty($filter['shop_ids']), function ($query) use ($filter) {
                $query->whereIn('pages.shop_id', $this->parseIds($filter['shop_ids']));
            })
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name', 'pancake_orders.customer_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                pancake_orders.customer_id as customer_id,
                MIN(pancake_orders.confirmed_at) as first_confirmed_at
            ');
    }

    private function parseIds(array|string $value): array
    {
        return is_array($value) ? $value : array_filter(explode(',', $value));
    }
}
