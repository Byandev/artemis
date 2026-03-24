<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Http\Sorts\Order\CustomerNameSort;
use App\Http\Sorts\Order\PageNameSort;
use App\Http\Sorts\Order\RiderNameSort;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\OrderForDelivery;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ForDeliveryController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $items = OrderForDelivery::with([
            'order'=> function ($query) {
                $query
                    ->select(['id', 'order_number', 'status_name', 'final_amount', 'parcel_status', 'tracking_code', 'delivery_attempts'])
                    ->with([
                        'shippingAddress' => function ($subQuery) {
                            $subQuery->with(['cityOrderSummary']);
                        },
                        'items' => function ($subQuery) {
                            $subQuery->select(['order_id', 'quantity', 'name']);
                        }
                        ]);
            },
            'conferrer' => function ($query) {
                $query->select(['id', 'name']);
            },
            'page' => function ($query) {
                $query->select(['id', 'name']);
            },
        ])->paginate(10);

        return Inertia::render('workspaces/rts/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
        ]);
    }

    public function updateStatus( Workspace $workspace, $id, Request $request){
        $orderForDelivery = OrderForDelivery::where('order_id', $id)->first();

        $orderForDelivery->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Status updated successfully');
    }
}
