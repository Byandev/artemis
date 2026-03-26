<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Http\Sorts\Order\ForDelivery\ConferrerNameSort;
use App\Http\Sorts\Order\ForDelivery\CustomerNameSort;
use App\Http\Sorts\Order\ForDelivery\LocationRtsRateSort;
use App\Http\Sorts\Order\ForDelivery\OrderAmountSort;
use App\Http\Sorts\Order\ForDelivery\OrderDeliveryAttemptSort;
use App\Http\Sorts\Order\ForDelivery\OrderNumberSort;
use App\Http\Sorts\Order\ForDelivery\OrderParcelStatusSort;
use App\Http\Sorts\Order\ForDelivery\OrderTrackingCodeSort;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Pancake\Models\OrderForDelivery;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ForDeliveryController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $items = QueryBuilder::for(OrderForDelivery::class)
            ->where('workspace_id', $workspace->id)
            ->with([
                'order' => function ($query) {
                    $query
                        ->select(['id', 'order_number', 'status_name', 'final_amount', 'parcel_status', 'tracking_code', 'delivery_attempts'])
                        ->with([
                            'shippingAddress' => function ($subQuery) {
                                $subQuery->with(['cityOrderSummary']);
                            },
                            'items' => function ($subQuery) {
                                $subQuery->select(['order_id', 'quantity', 'name']);
                            },
                        ]);
                },
                'conferrer' => function ($query) {
                    $query->select(['id', 'name']);
                },
                'page' => function ($query) {
                    $query->select(['id', 'name']);
                },
            ])
            ->allowedFilters([
                AllowedFilter::exact('page_id'),
                AllowedFilter::exact('shop_id'),
            ])
            ->allowedSorts([
                'status',
                'rider_name',
                AllowedSort::custom('conferrer_name', new ConferrerNameSort),
                AllowedSort::custom('order_number', new OrderNumberSort),
                AllowedSort::custom('order_parcel_status', new OrderParcelStatusSort),
                AllowedSort::custom('order_delivery_attempts', new OrderDeliveryAttemptSort),
                AllowedSort::custom('order_tracking_code', new OrderTrackingCodeSort),
                AllowedSort::custom('order_final_amount', new OrderAmountSort),
                AllowedSort::custom('order_shipping_address_full_name', new CustomerNameSort),
                AllowedSort::custom('order_shipping_address_city_order_summary_rts_rate', new LocationRtsRateSort),
            ])
            ->paginate(10);

        return Inertia::render('workspaces/rts/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
        ]);
    }

    public function updateStatus(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::where('order_id', $id)->first();

        $orderForDelivery->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Status updated successfully');
    }

    public  function analytics(Workspace $workspace, Request $request)
    {

        $stats = OrderForDelivery::query()
            ->from('pancake_order_for_delivery as ofd')
            ->join('pancake_orders as o', 'o.id', '=', 'ofd.order_id')
            ->where('ofd.workspace_id', $workspace->id)
            ->selectRaw("
        SUM(ofd.assignee_id IS NOT NULL) as assigned_orders,

        SUM(CASE
            WHEN ofd.status IN ('CX RINGING', 'RIDER RINGING')
            THEN 1 ELSE 0
        END) as total_called,

        SUM(CASE WHEN ofd.status = 'PENDING' THEN 1 ELSE 0 END) as total_pending,

        SUM(CASE WHEN o.parcel_status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,

        SUM(CASE WHEN ofd.status = 'RETURNING' THEN 1 ELSE 0 END) as total_returning,

        SUM(CASE WHEN o.parcel_status = 'undeliverable' THEN 1 ELSE 0 END) as total_undeliverable,

        SUM(CASE WHEN o.parcel_status = 'out_for_delivery' THEN 1 ELSE 0 END) as total_out_for_delivery
    ")
            ->first();

        return Inertia::render('workspaces/rts/rmo-management/analytics', [
            'workspace' => $workspace,
            'stats' => $stats,
        ]);
    }
    
}
