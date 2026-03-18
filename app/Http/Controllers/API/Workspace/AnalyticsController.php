<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $dateRange = $request->array('date_range', []);
        $filter = $request->array('filter', []);
        $metrics = $request->array('metric', []);
        $comparePrevious = $request->boolean('compare_previous', true);

        $cacheKey = json_encode([
            'workspace_id' => $workspace->id,
            'date_range' => $dateRange,
            'filter' => $filter,
            'metric' => $metrics,
            'compare_previous' => $comparePrevious,
        ]);

        $data = Cache::remember($cacheKey, 5 * 60, function () use (
            $workspace,
            $dateRange,
            $filter,
            $metrics,
            $comparePrevious
        ) {
            $current = $workspace
                ->metrics($dateRange, $filter)
                ->extract($metrics);

            if (! $comparePrevious) {
                return [
                    'current' => $current,
                ];
            }

            $previousDateRange = $this->getPreviousDateRange($dateRange);

            $previous = $workspace
                ->metrics($previousDateRange, $filter)
                ->extract($metrics);

            $change = [];

            foreach ($metrics as $metric) {
                $currentValue = (float) ($current[$metric] ?? 0);
                $previousValue = (float) ($previous[$metric] ?? 0);

                $change[$metric] = $this->getPercentageChange($currentValue, $previousValue);
            }

            return [
                'current' => $current,
                'change' => $change,
            ];
        });

        return response()->json($data);
    }

    private function getPreviousDateRange(array $dateRange): array
    {
        $start = Carbon::parse($dateRange['start_date']);
        $end = Carbon::parse($dateRange['end_date']);

        $days = $start->diffInDays($end) + 1;

        return [
            'start_date' => $start->copy()->subDays($days)->format('Y-m-d'),
            'end_date' => $start->copy()->subDay()->format('Y-m-d'),
        ];
    }

    private function getPercentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    public function breakdown(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->breakdown(
            $request->input('metric', 'totalSales'),
            $request->input('group', 'daily')
        );

        return response()->json(['data' => $data]);
    }

    public function perPage(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perPage(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }

    public function perShop(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perShop(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }

    public function perUser(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perUser(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }
}
