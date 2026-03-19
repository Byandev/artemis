<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Pancake\Models\User as PancakeUser;

class CsrPerformanceController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    public function publicIndex(Request $request, Workspace $workspace)
    {
        $validated = $this->validatedFilters($request);
        $validated = $this->normalizePublicFilters($validated);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    public function publicCsrIndex(Workspace $workspace)
    {
        $data = PancakeUser::query()
            ->select(['id', 'name'])
            ->whereHas('assignedOrders', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'csr_id' => (string) $user->id,
                'name' => $user->name,
            ])
            ->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    private function buildLeaderboard(Workspace $workspace, array $validated): Collection
    {
        $period = $validated['period'] ?? 'monthly';
        $sortBy = $validated['sort_by'] ?? 'rank';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $periodStart = $this->resolvedPeriodStart($validated, $period);

        $users = PancakeUser::query()
            ->select(['id', 'name', 'fb_id'])
            ->whereNotNull('fb_id')
            ->whereHas('assignedOrders', function ($query) use ($workspace, $validated) {
                $this->applyLeaderboardOrderFilters($query, $workspace, $validated);
            })
            ->withCount(['assignedOrders as total_orders' => function ($query) use ($workspace, $validated) {
                $this->applyLeaderboardOrderFilters($query, $workspace, $validated);
            }])
            ->withSum(['assignedOrders as total_sales' => function ($query) use ($workspace, $validated) {
                $this->applyLeaderboardOrderFilters($query, $workspace, $validated);
            }], 'final_amount')
            ->get();

        $rows = $users
            ->map(function ($user) use ($periodStart) {
                return (object) [
                    'period_start' => $periodStart,
                    'csr_id' => $user->id,
                    'name' => $user->name,
                    'total_orders' => (int) ($user->total_orders ?? 0),
                    'total_sales' => (float) ($user->total_sales ?? 0),
                ];
            })
            ->sortBy('period_start')
            ->values();

        return $this->rankRows($rows, $period, $sortBy, $sortDir);
    }

    private function applyLeaderboardOrderFilters($query, Workspace $workspace, array $validated): void
    {
        $query->where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->when(
                ! empty($validated['start_date']),
                fn ($q) => $q->whereDate('confirmed_at', '>=', $validated['start_date'])
            )
            ->when(
                ! empty($validated['end_date']),
                fn ($q) => $q->whereDate('confirmed_at', '<=', $validated['end_date'])
            );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'sort_by' => ['nullable', 'in:rank,name,sales,orders'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
    }

    private function normalizePublicFilters(array $validated): array
    {
        $period = $validated['period'] ?? 'monthly';

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        if ($startDate === null && $endDate === null) {
            [$startDate, $endDate] = match ($period) {
                'daily' => [Carbon::today()->toDateString(), Carbon::today()->toDateString()],
                'weekly' => [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->endOfWeek()->toDateString()],
                default => [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()],
            };
        } elseif ($startDate === null) {
            $startDate = $endDate;
        } elseif ($endDate === null) {
            $endDate = $startDate;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['The end_date must be after or equal to start_date.'],
            ]);
        }

        if ($start->diffInDays($end) > 92) {
            throw ValidationException::withMessages([
                'start_date' => ['Public queries are limited to a maximum 93-day date range.'],
            ]);
        }

        $validated['start_date'] = $start->toDateString();
        $validated['end_date'] = $end->toDateString();

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
        $startDate = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
        $endDate = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');

        $users = PancakeUser::query()
            ->withCount([
                'orders' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
                }
            ])
            ->withSum([
                'orders as sales' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
                }
            ], 'final_amount')
            ->orderByDesc('sales')
            ->get();

        return $users;
    }
}
