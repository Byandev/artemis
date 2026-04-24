<?php

namespace App\Metrics\ParcelJourney;

use Illuminate\Support\Facades\DB;

final class TotalForDeliveryCount
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return $this->baseQuery($workspaceId, $date_range)->count();
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periodSql = match ($group) {
            'daily' => 'DATE(parcel_journeys.created_at)',
            'weekly' => "DATE_FORMAT(parcel_journeys.created_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(parcel_journeys.created_at, '%Y-%m')",
            default => 'DATE(parcel_journeys.created_at)',
        };

        return $this->baseQuery($workspaceId, $date_range)
            ->selectRaw("$periodSql as period, COUNT(*) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->selectRaw('pages.id as page_id, pages.name as page_name, COUNT(*) as value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, COUNT(*) as value')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range)
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name, COUNT(*) as value')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range)
    {
        return DB::table('parcel_journeys')
            ->join('pancake_orders', 'pancake_orders.id', '=', 'parcel_journeys.order_id')
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('parcel_journeys.status', 'On Delivery')
            ->whereBetween('parcel_journeys.created_at', [
                $date_range['start_date'].' 00:00:00',
                $date_range['end_date'].' 23:59:59',
            ]);
    }
}
