<?php

namespace App\Metrics\Orders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TimeToFirstOrder
{
    /**
     * Avg time from customer.created_at -> customer's true first confirmed order (in HOURS).
     * Only includes customers whose first confirmed order falls within the selected range.
     *
     * Reads from workspace_customer_facts. page/shop filters fall back to live because
     * customer_facts only stores first_confirmed_page_id (single page), not customer×page.
     */
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        if ($this->hasEntityFilter($filter)) {
            return $this->computeLive($workspaceId, $date_range, $filter);
        }

        $row = $this->rollupBase($workspaceId, $date_range)
            ->selectRaw('
                ROUND(
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, customer_created_at, first_confirmed_at)), 0),
                    2
                ) as value
            ')
            ->first();

        return (float) ($row->value ?? 0);
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        if ($this->hasEntityFilter($filter)) {
            return $this->breakdownLive($workspaceId, $date_range, $filter, $group);
        }

        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(first_confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(first_confirmed_at, '%Y-%m')",
            default => 'DATE(first_confirmed_at)',
        };

        return $this->rollupBase($workspaceId, $date_range)
            ->selectRaw("
                $periodSql as period,
                ROUND(
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, customer_created_at, first_confirmed_at)), 0),
                    2
                ) as value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    /**
     * perPage: groups by first_confirmed_page_id (customer's first-order page).
     * Semantic differs slightly from live (live groups by page the customer ever ordered from).
     */
    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBase($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'workspace_customer_facts.first_confirmed_page_id')
            ->whereNotNull('workspace_customer_facts.first_confirmed_page_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                ROUND(
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, workspace_customer_facts.customer_created_at, workspace_customer_facts.first_confirmed_at)), 0),
                    2
                ) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBase($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'workspace_customer_facts.first_confirmed_page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                ROUND(
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, workspace_customer_facts.customer_created_at, workspace_customer_facts.first_confirmed_at)), 0),
                    2
                ) as value
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->rollupBase($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'workspace_customer_facts.first_confirmed_page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                ROUND(
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, workspace_customer_facts.customer_created_at, workspace_customer_facts.first_confirmed_at)), 0),
                    2
                ) as value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function rollupBase(int $workspaceId, array $dateRange)
    {
        return DB::table('workspace_customer_facts')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('first_confirmed_at', [
                $dateRange['start_date'].' 00:00:00',
                $dateRange['end_date'].' 23:59:59',
            ])
            ->whereNotNull('customer_created_at');
    }

    private function hasEntityFilter(array $filter): bool
    {
        return ! empty($filter['page_ids']) || ! empty($filter['shop_ids']);
    }

    private function computeLive(int $workspaceId, array $date_range, array $filter): float
    {
        $row = DB::query()
            ->fromSub($this->firstOrderPerCustomerQuery($workspaceId, $filter), 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw('
                ROUND(COALESCE(AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)), 0), 2) as value
            ')
            ->first();

        return (float) ($row->value ?? 0);
    }

    private function breakdownLive(int $workspaceId, array $date_range, array $filter, string $group)
    {
        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(t.first_confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(t.first_confirmed_at, '%Y-%m')",
            default => 'DATE(t.first_confirmed_at)',
        };

        return DB::query()
            ->fromSub($this->firstOrderPerCustomerQuery($workspaceId, $filter), 't')
            ->join('pancake_customers as c', 'c.customer_id', '=', 't.customer_id')
            ->whereNotNull('c.created_at')
            ->whereBetween('t.first_confirmed_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ])
            ->selectRaw("
                $periodSql as period,
                ROUND(COALESCE(AVG(TIMESTAMPDIFF(HOUR, c.created_at, t.first_confirmed_at)), 0), 2) as value
            ")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

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
            ->selectRaw('pancake_orders.customer_id, MIN(pancake_orders.confirmed_at) as first_confirmed_at');
    }

    private function parseIds(array|string $value): array
    {
        return is_array($value) ? $value : array_filter(explode(',', $value));
    }
}
