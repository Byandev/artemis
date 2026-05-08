<?php

namespace App\Metrics\ParcelJourney;

use App\Support\Metrics\OrdersFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sum of final_amount for distinct pancake_orders whose "out for delivery" window overlaps the date range.
 * Window = [first_delivery_attempt, COALESCE(delivered_at, returning_at, NOW())].
 *
 * @see TotalForDeliveryCount for the matching count metric and overlap rationale.
 */
final class TotalForDeliveryAmount
{
    public function compute(int $workspaceId, array $date_range, array $filter): float
    {
        return round(
            (float) $this->baseQuery($workspaceId, $date_range, $filter)
                ->sum('pancake_orders.final_amount'),
            2
        );
    }

    public function breakdown(int $workspaceId, array $date_range, array $filter, string $group = 'daily')
    {
        $periods = $this->generatePeriods($date_range, $group);

        return collect($periods)->map(function (array $period) use ($workspaceId, $filter) {
            return (object) [
                'period' => $period['label'],
                'value' => round(
                    (float) $this->baseQuery($workspaceId, [
                        'start_date' => $period['start'],
                        'end_date' => $period['end'],
                    ], $filter)->sum('pancake_orders.final_amount'),
                    2
                ),
            ];
        })->values();
    }

    public function perPage(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->selectRaw('pages.id as page_id, pages.name as page_name, SUM(pancake_orders.final_amount) as value')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, SUM(pancake_orders.final_amount) as value')
            ->whereNotNull('pages.shop_id')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $date_range, array $filter)
    {
        return $this->baseQuery($workspaceId, $date_range, $filter, true)
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->selectRaw('users.id as user_id, users.name as user_name, SUM(pancake_orders.final_amount) as value')
            ->whereNotNull('pages.owner_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function baseQuery(int $workspaceId, array $date_range, array $filter, bool $forceJoinPages = false)
    {
        $start = $date_range['start_date'].' 00:00:00';
        $end = $date_range['end_date'].' 23:59:59';

        return DB::table('pancake_orders')
            ->tap(fn ($q) => OrdersFilter::joinAndApply($q, $filter, $forceJoinPages))
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.first_delivery_attempt')
            ->where('pancake_orders.first_delivery_attempt', '<=', $end)
            ->whereRaw('COALESCE(pancake_orders.delivered_at, pancake_orders.returning_at, NOW()) >= ?', [$start]);
    }

    /**
     * @return array<int, array{label: string, start: string, end: string}>
     */
    private function generatePeriods(array $date_range, string $group): array
    {
        $start = Carbon::parse($date_range['start_date'])->startOfDay();
        $end = Carbon::parse($date_range['end_date'])->startOfDay();
        $periods = [];

        if ($group === 'monthly') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor <= $last) {
                $periods[] = [
                    'label' => $cursor->format('Y-m'),
                    'start' => $cursor->copy()->startOfMonth()->toDateString(),
                    'end' => $cursor->copy()->endOfMonth()->toDateString(),
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
                    'start' => $cursor->copy()->toDateString(),
                    'end' => $cursor->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                ];
                $cursor->addWeek();
            }

            return $periods;
        }

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periods[] = [
                'label' => $cursor->format('Y-m-d'),
                'start' => $cursor->toDateString(),
                'end' => $cursor->toDateString(),
            ];
            $cursor->addDay();
        }

        return $periods;
    }
}
