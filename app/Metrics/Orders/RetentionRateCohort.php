<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class RetentionRateCohort
{
    abstract protected function days(): int;

    /**
     * Rolling cohort retention.
     *
     * Cohort  : customers whose first-ever order is between (today - 2N days) and (today - N days).
     * Check   : did they order again within N days of that first order?
     * Result  : retained / new customers in cohort.
     *
     * Reads from workspace_customer_facts (cohort source) + workspace_customer_activity_daily
     * (retention activity). page/shop filter uses first_confirmed_page_id (approximation:
     * cohort = customers whose GLOBAL first order was on that page/shop).
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $days = $this->days();
        $cohortStart = Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString();
        $cohortEnd = Carbon::now()->subDays($days)->startOfDay()->toDateTimeString();

        return $this->computeForWindow($workspaceId, $filter, $cohortStart, $cohortEnd);
    }

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

    public function perPage(int $workspaceId, array $dateRange, array $filter)
    {
        [$cohortStart, $cohortEnd] = $this->rollingCohortWindow();

        return $this->cohortWithRetention($workspaceId, $filter, $cohortStart, $cohortEnd)
            ->join('pages', 'pages.id', '=', 'cf.first_confirmed_page_id')
            ->whereNotNull('cf.first_confirmed_page_id')
            ->selectRaw('
                pages.id as page_id,
                pages.name as page_name,
                COUNT(DISTINCT CASE WHEN cf.retained = 1 THEN cf.customer_id END) as value
            ')
            ->groupBy('pages.id', 'pages.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perShop(int $workspaceId, array $dateRange, array $filter)
    {
        [$cohortStart, $cohortEnd] = $this->rollingCohortWindow();

        return $this->cohortWithRetention($workspaceId, $filter, $cohortStart, $cohortEnd)
            ->join('pages', 'pages.id', '=', 'cf.first_confirmed_page_id')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereNotNull('pages.shop_id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                COUNT(DISTINCT CASE WHEN cf.retained = 1 THEN cf.customer_id END) as value
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderByDesc('value')
            ->get();
    }

    public function perUser(int $workspaceId, array $dateRange, array $filter)
    {
        [$cohortStart, $cohortEnd] = $this->rollingCohortWindow();

        return $this->cohortWithRetention($workspaceId, $filter, $cohortStart, $cohortEnd)
            ->join('pages', 'pages.id', '=', 'cf.first_confirmed_page_id')
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->whereNotNull('pages.owner_id')
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                COUNT(DISTINCT CASE WHEN cf.retained = 1 THEN cf.customer_id END) as value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('value')
            ->get();
    }

    private function rollingCohortWindow(): array
    {
        $days = $this->days();

        return [
            Carbon::now()->subDays($days * 2)->startOfDay()->toDateTimeString(),
            Carbon::now()->subDays($days)->startOfDay()->toDateTimeString(),
        ];
    }

    private function computeForWindow(int $workspaceId, array $filter, string $startAt, string $endExclusive): float
    {
        $row = $this->cohortWithRetention($workspaceId, $filter, $startAt, $endExclusive)
            ->selectRaw('
                COUNT(*) as total,
                SUM(cf.retained) as retained
            ')
            ->first();

        $total = (int) ($row->total ?? 0);
        if ($total === 0) {
            return 0.0;
        }

        return round((int) ($row->retained ?? 0) / $total, 4);
    }

    /**
     * Returns a subquery builder aliased as `cf` containing:
     *   customer_id, first_confirmed_page_id,
     *   retained (0/1) — 1 if customer had another order within N days of first
     */
    private function cohortWithRetention(int $workspaceId, array $filter, string $cohortStart, string $cohortEnd)
    {
        $days = $this->days();
        $pageIds = $this->resolveIds($filter['page_ids'] ?? []);
        $shopIds = $this->resolveIds($filter['shop_ids'] ?? []);

        $cohort = DB::table('workspace_customer_facts')
            ->where('workspace_id', $workspaceId)
            ->where('first_confirmed_at', '>=', $cohortStart)
            ->where('first_confirmed_at', '<', $cohortEnd)
            ->selectRaw("
                workspace_id,
                customer_id,
                first_confirmed_at,
                first_confirmed_page_id,
                CASE WHEN EXISTS (
                    SELECT 1 FROM workspace_customer_activity_daily a
                    WHERE a.workspace_id = workspace_customer_facts.workspace_id
                      AND a.customer_id = workspace_customer_facts.customer_id
                      AND a.date >= DATE(workspace_customer_facts.first_confirmed_at)
                      AND a.date <= DATE_ADD(DATE(workspace_customer_facts.first_confirmed_at), INTERVAL {$days} DAY)
                      AND (
                        a.date > DATE(workspace_customer_facts.first_confirmed_at)
                        OR a.confirmed_count >= 2
                      )
                ) THEN 1 ELSE 0 END AS retained
            ");

        $query = DB::query()->fromSub($cohort, 'cf');

        if (! empty($pageIds)) {
            $query->whereIn('cf.first_confirmed_page_id', $pageIds);
        }
        if (! empty($shopIds)) {
            $query->whereIn('cf.first_confirmed_page_id', function ($sub) use ($shopIds) {
                $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
            });
        }

        return $query;
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
