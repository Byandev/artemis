<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderItem;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $totalOrders = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })->whereNotNull('confirmed_at')->count();

        $totalSales = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })
            ->whereNotNull('confirmed_at')
            ->sum('final_amount');

        $totalQuantity = OrderItem::whereHas('order', function ($query) use ($request) {
            $query->whereHas('page', function ($query) use ($request) {
                $query->where('workspace_id', $request->workspace->id);
            })->whereNotNull('confirmed_at');
        })->count();

        $totalDeliveredAmount = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })
            ->whereNotNull('delivered_at')
            ->sum('final_amount');

        $totalInTransitAmount = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })
            ->whereNotNull('shipped_at')
            ->whereNull('delivered_at')
            ->whereNull('returning_at')
            ->sum('final_amount');

        $totalReturningAmount = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })
            ->whereNotNull('returning_at')
            ->whereNull('returned_at')
            ->sum('final_amount');

        $totalReturnedAmount = Order::whereHas('page', function ($query) use ($request) {
            $query->where('workspace_id', $request->workspace->id);
        })
            ->whereNotNull('returning_at')
            ->whereNotNull('returned_at')
            ->sum('final_amount');

        return response()->json([
            'totalOrders' => $totalOrders,
            'totalSales' => $totalSales,
            'totalQuantity' => $totalQuantity,
            'aov' => $totalSales / $totalOrders,
            'totalDeliveredAmount' => $totalDeliveredAmount,
            'totalReturningAmount' => $totalReturningAmount,
            'totalReturnedAmount' => $totalReturnedAmount,
            'rts_rate' => ($totalReturningAmount + $totalReturnedAmount) / ($totalReturningAmount + $totalReturnedAmount + $totalDeliveredAmount),
        ]);
    }
}
