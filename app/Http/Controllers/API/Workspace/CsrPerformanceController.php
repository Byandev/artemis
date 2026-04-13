<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Pancake\Models\User;

class CsrPerformanceController extends Controller
{
    public function publicIndex(Request $request, Workspace $workspace)
    {
        $validated = $this->validatedFilters($request);
        $validated = $this->normalizePublicFilters($validated);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    private function buildLeaderboard(Workspace $workspace, array $validated): Collection
    {
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        $startAt = Carbon::parse($startDate)->startOfDay();
        $endAt = Carbon::parse($endDate)->endOfDay();

        $ordersAgg = DB::table('pancake_orders')
            ->selectRaw('confirmed_by, COUNT(*) as total_orders, COALESCE(SUM(final_amount), 0) as total_sales')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('confirmed_at', [$startAt, $endAt])
            ->groupBy('confirmed_by');

        $users = User::query()
            ->select([
                'pancake_users.id',
                'pancake_users.name',
                DB::raw('agg.total_orders'),
                DB::raw('agg.total_sales'),
            ])
            ->joinSub($ordersAgg, 'agg', function ($join) {
                $join->on('agg.confirmed_by', '=', 'pancake_users.id');
            })
            ->orderByDesc('agg.total_sales')
            ->orderByDesc('agg.total_orders')
            ->orderBy('pancake_users.name')
            ->get();

        $rank = 1;
        foreach ($users as $user) {
            $user->csr_id = (string) $user->id;
            $user->rank = $rank++;
            $user->total_orders = (int) ($user->total_orders ?? 0);
            $user->total_sales = (float) ($user->total_sales ?? 0);
            $user->period_start = $startDate;
            $user->period = 'custom';
        }

        return $users->values();
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages([
                'end_date' => ['Date range cannot exceed 31 days.'],
            ]);
        }

        return $validated;
    }

    private function rankRows(Collection $rows, string $period, string $sortBy, string $sortDir): Collection
    {
        return $rows
            ->groupBy('period_start')
            ->flatMap(function (Collection $group) use ($period, $sortBy, $sortDir) {
                return $this->sortRows($group, $sortBy, $sortDir)
                    ->values()
                    ->map(function ($row, int $index) use ($period) {
                        return [
                            'csr_id' => (string) $row->csr_id,
                            'period' => $period,
                            'period_start' => $row->period_start,
                            'rank' => $index + 1,
                            'name' => $row->name,
                            'total_orders' => (int) $row->total_orders,
                            'total_sales' => (float) $row->total_sales,
                        ];
                    });
            })
            ->values();
    }

    private function sortRows(Collection $rows, string $sortBy, string $sortDir): Collection
    {
        $direction = $sortDir === 'asc' ? 1 : -1;

        return $rows->sort(function ($left, $right) use ($sortBy, $direction) {
            $comparison = match ($sortBy) {
                'name' => strcasecmp((string) $left->name, (string) $right->name),
                'sales' => $this->compareNumbers((float) $left->total_sales, (float) $right->total_sales),
                'orders' => $this->compareNumbers((int) $left->total_orders, (int) $right->total_orders),
                default => $this->compareRank($left, $right),
            };

            if ($comparison === 0) {
                $comparison = strcasecmp((string) $left->name, (string) $right->name);
            }

            return $comparison * $direction;
        })->values();
    }

    private function compareRank(object $left, object $right): int
    {
        $ordersComparison = $this->compareNumbers((int) $left->total_orders, (int) $right->total_orders);

        if ($ordersComparison !== 0) {
            return $ordersComparison;
        }

        return $this->compareNumbers((float) $left->total_sales, (float) $right->total_sales);
    }

    private function compareNumbers(int|float $left, int|float $right): int
    {
        return $left <=> $right;
    }

    private function resolvedPeriodStart(array $validated, string $period): string
    {
        $base = ! empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : Carbon::now();

        return match ($period) {
            'daily' => $base->toDateString(),
            'weekly' => $base->startOfWeek()->toDateString(),
            default => $base->startOfMonth()->toDateString(),
        };
    }

    public function leaderboards()
    {
        $startDate = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $endDate = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        return PancakeUser::query()
            ->withCount([
                'orders' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
                },
            ])
            ->withSum([
                'orders as sales' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
                },
            ], 'final_amount')
            ->whereHas('orders', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('confirmed_at', [$startDate, $endDate]);
            })
            ->orderByDesc('sales')
            ->get();
    }

    public function leaderboardsGroupByCalled()
    {
        $startDate = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $endDate = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        return PancakeUser::query()
            ->withCount([
                'assignedOrderForDelivery' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('delivery_date', [$startDate, $endDate]);
                },
            ])
            ->having('assigned_order_for_delivery_count', '>', 0) // Only get users with at least 1 assigned order
            ->orderByDesc('assigned_order_for_delivery_count')
            ->get();
    }

    public function leaderboardsGroupByDelivered()
    {
        $startDate = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $endDate = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        return PancakeUser::query()
            ->withCount([
                'assignedOrderForDelivery' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('delivery_date', [$startDate, $endDate])
                        ->where('status', 'Delivered');
                },
            ])
            ->having('assigned_order_for_delivery_count', '>', 0) // Only get users with at least 1 delivered order
            ->orderByDesc('assigned_order_for_delivery_count')
            ->get();
    }
}
