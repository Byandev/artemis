<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Http\Requests\InventoryDashboardRequest;
use Modules\Inventory\Queries\InventoryDashboardQuery;

/**
 * Per-statistic endpoints for the inventory dashboard. Each widget is resolved
 * on its own so the frontend can load, skeleton, and refresh it independently —
 * one focused query per request instead of one heavy page load. Authorization,
 * validation, and range parsing all live in InventoryDashboardRequest.
 */
class InventoryDashboardStatsController extends Controller
{
    public function kpis(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->kpis());
    }

    public function movement(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->movementSeries());
    }

    public function poStatus(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->poStatusFunnel());
    }

    public function fulfillment(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->fulfillmentBreakdown());
    }

    public function shrinkage(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->shrinkageTrend());
    }

    public function stockHealth(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->stockHealth());
    }

    public function upcomingDeliveries(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->upcomingDeliveries());
    }

    public function recentAdjustments(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->recentAdjustments());
    }

    public function topDiscrepancies(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->topDiscrepancies());
    }

    public function alerts(InventoryDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->query($request, $workspace)->alertsFeed());
    }

    private function query(InventoryDashboardRequest $request, Workspace $workspace): InventoryDashboardQuery
    {
        [$start, $end] = $request->range();

        return new InventoryDashboardQuery($workspace, $start, $end);
    }
}
