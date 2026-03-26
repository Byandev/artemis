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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User;
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
                'assignee' => function ($query) {
                    $query->select(['id', 'name']);
                },
                'page' => function ($query) {
                    $query->select(['id', 'name']);
                },
            ])
            ->allowedFilters([
                AllowedFilter::exact('page_id'),
                AllowedFilter::exact('shop_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('parcel_status', function ($query, $value) {
                    $query->whereHas('order', function ($orderQuery) use ($value) {
                        $orderQuery->where('parcel_status', $value);
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->whereHas('order', function ($orderQuery) use ($value) {
                            $orderQuery->where('order_number', 'LIKE', "%{$value}%")
                                ->orWhere('tracking_code', 'LIKE', "%{$value}%");
                        })
                            ->orWhere('rider_name', 'LIKE', "%{$value}%")
                            ->orWhereHas('conferrer', function ($conferrerQuery) use ($value) {
                                $conferrerQuery->where('name', 'LIKE', "%{$value}%");
                            });
                    });
                }),
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
            'query' => [
                ...$request->only(['sort', 'perPage', 'page',]),
                'filter' => $request->input('filter', []),
            ]

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

    public function publicUpdateStatus(Workspace $workspace, $id, Request $request)
    {
        $isRemoving = $request->has('removeAssignee') && $request->removeAssignee;

        // Require userId unless explicitly removing the assignee
        if (!$isRemoving && (!$request->has('userId') || !$request->userId)) {
            return redirect()->back()->with('error', 'Please select a user before updating.');
        }

        // Find the order
        $orderForDelivery = OrderForDelivery::where('order_id', $id)->first();

        if (!$orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        // Update status and assignee
        $orderForDelivery->update([
            'status' => $request->status,
            'assignee_id' => $isRemoving ? null : $request->userId,
        ]);

        return redirect()->back()->with('success', 'Status updated successfully');
    }

    public function public(Request $request, Workspace $workspace)
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
                'assignee' => function ($query) {
                    $query->select(['id', 'name']);
                },
                'page' => function ($query) {
                    $query->select(['id', 'name']);
                },
            ])
            ->allowedFilters([
                AllowedFilter::exact('page_id'),
                AllowedFilter::exact('shop_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('parcel_status', function ($query, $value) {
                    $query->whereHas('order', function ($orderQuery) use ($value) {
                        $orderQuery->where('parcel_status', $value);
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->whereHas('order', function ($orderQuery) use ($value) {
                            $orderQuery->where('order_number', 'LIKE', "%{$value}%")
                                ->orWhere('tracking_code', 'LIKE', "%{$value}%");
                        })
                            ->orWhere('rider_name', 'LIKE', "%{$value}%")
                            ->orWhereHas('conferrer', function ($conferrerQuery) use ($value) {
                                $conferrerQuery->where('name', 'LIKE', "%{$value}%");
                            });
                    });
                }),
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

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page',]),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
        ]);
    }
}
