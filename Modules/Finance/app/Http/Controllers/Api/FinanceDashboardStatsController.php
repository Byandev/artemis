<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Modules\Finance\Http\Requests\FinanceDashboardRequest;
use Modules\Finance\Queries\FinanceDashboardQuery;

/**
 * Per-statistic endpoints for the finance dashboard. Each widget is resolved on
 * its own so the frontend can load, skeleton, and refresh it independently —
 * one focused query per request instead of one heavy page load. Authorization,
 * validation, and range parsing all live in FinanceDashboardRequest.
 */
class FinanceDashboardStatsController extends Controller
{
    public function kpis(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->kpis());
    }

    public function cashFlow(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->cashFlowSeries());
    }

    public function balanceHistory(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->balanceHistory());
    }

    public function expenseBreakdown(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->expenseBreakdown());
    }

    public function incomeBreakdown(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->incomeBreakdown());
    }

    public function topMovements(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->topMovements());
    }

    public function reconciliation(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->reconciliation());
    }

    public function profitability(FinanceDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->profitability());
    }

    private function query(FinanceDashboardRequest $request, Workspace $workspace): FinanceDashboardQuery
    {
        [$start, $end] = $request->range();

        return new FinanceDashboardQuery($workspace, $start, $end);
    }
}
