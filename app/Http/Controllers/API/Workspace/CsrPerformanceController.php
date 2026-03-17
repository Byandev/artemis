<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    private function buildLeaderboard(Workspace $workspace, array $validated): Collection
    {

        $period = $validated['period'] ?? 'monthly';
        $sortBy = $validated['sort_by'] ?? 'rank';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $periodExpression = $this->periodExpression($period);

        $rows = DB::table('customer_service_representatives as csr')
            ->join('orders', 'orders.assignee_id', '=', 'csr.id')
            ->where('orders.workspace_id', $workspace->id)
            ->whereNotNull('orders.confirmed_at')
            ->when(
                ! empty($validated['start_date']),
                fn ($query) => $query->whereDate('orders.confirmed_at', '>=', $validated['start_date'])
            )
            ->when(
                ! empty($validated['end_date']),
                fn ($query) => $query->whereDate('orders.confirmed_at', '<=', $validated['end_date'])
            )
            ->selectRaw("{$periodExpression} as period_start")
            ->selectRaw('csr.id as csr_id, csr.name')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) as total_sales')
            ->groupBy(DB::raw($periodExpression), 'csr.id', 'csr.name')
            ->orderBy('period_start')
            ->get();

        return $this->rankRows($rows, $period, $sortBy, $sortDir);
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

    private function rankRows(Collection $rows, string $period, string $sortBy, string $sortDir): Collection
    {
        return $rows
            ->groupBy('period_start')
            ->flatMap(function (Collection $group) use ($period, $sortBy, $sortDir) {
                return $this->sortRows($group, $sortBy, $sortDir)
                    ->values()
                    ->map(function ($row, int $index) use ($period) {
                        return [
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

    private function periodExpression(string $period): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'sqlite' => match ($period) {
                'daily' => "date(orders.confirmed_at)",
                'weekly' => "date(orders.confirmed_at, '-' || ((strftime('%w', orders.confirmed_at) + 6) % 7) || ' days')",
                default => "strftime('%Y-%m-01', orders.confirmed_at)",
            },
            'pgsql' => match ($period) {
                'daily' => 'DATE(orders.confirmed_at)',
                'weekly' => "TO_CHAR(date_trunc('week', orders.confirmed_at), 'YYYY-MM-DD')",
                default => "TO_CHAR(date_trunc('month', orders.confirmed_at), 'YYYY-MM-01')",
            },
            default => match ($period) {
                'daily' => 'DATE(orders.confirmed_at)',
                'weekly' => 'DATE(DATE_SUB(orders.confirmed_at, INTERVAL WEEKDAY(orders.confirmed_at) DAY))',
                default => "DATE_FORMAT(orders.confirmed_at, '%Y-%m-01')",
            },
        };
    }
}