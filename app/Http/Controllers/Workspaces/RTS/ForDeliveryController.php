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
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $values = array_map('strtolower', $values);
                    $query->whereHas('order', function ($orderQuery) use ($values) {
                        $orderQuery->whereIn('parcel_status', $values);
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
                AllowedFilter::callback('page_id', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('page_id', $values);
                }),
                AllowedFilter::exact('shop_id'),
                AllowedFilter::callback('status', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('status', $values);
                }),
                AllowedFilter::callback('parcel_status', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $values = array_map('strtolower', $values);
                    $query->whereHas('order', function ($orderQuery) use ($values) {
                        $orderQuery->whereIn('parcel_status', $values);
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

        $workspaceId = $workspace->id;

// 1️⃣ Total for delivery today
        $totalForDeliveryToday = OrderForDelivery::query()
            ->from('pancake_order_for_delivery as ofd')
            ->join('pancake_orders as o', 'o.id', '=', 'ofd.order_id')
            ->where('ofd.workspace_id', $workspaceId)
            ->where('o.parcel_status', 'out_for_delivery')
            ->whereDate('o.updated_at', now())  // or created_at if you prefer
            ->count();

// 2️⃣ All-time stats (no date filter)
        $stats = OrderForDelivery::query()
            ->from('pancake_order_for_delivery as ofd')
            ->join('pancake_orders as o', 'o.id', '=', 'ofd.order_id')
            ->where('ofd.workspace_id', $workspaceId)
            ->selectRaw("
        COUNT(*) as total,
        SUM(CASE WHEN ofd.status != 'PENDING' THEN 1 ELSE 0 END) as total_not_pending,
        SUM(CASE WHEN o.parcel_status IS NOT NULL THEN 1 ELSE 0 END) as total_parcel,
        SUM(CASE WHEN o.parcel_status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,
        SUM(CASE WHEN o.parcel_status = 'returning' THEN 1 ELSE 0 END) as total_returning,
        SUM(CASE WHEN o.parcel_status = 'undeliverable' THEN 1 ELSE 0 END) as total_undeliverable,
        SUM(CASE WHEN o.parcel_status = 'problematic' THEN 1 ELSE 0 END) as total_problematic
    ")
            ->first();

// 3️⃣ Calculate rates
        $total = (int) ($stats->total ?: 1);
        $totalParcel = (int) ($stats->total_parcel ?: 1);

        $stats->called_rate = round(((int) $stats->total_not_pending / $total) * 100, 1);
        $stats->successful_rate = round(((int) $stats->total_delivered / $totalParcel) * 100, 1);
        $stats->unsuccessful_rate = round((
                ((int) $stats->total_problematic + (int) $stats->total_returning + (int) $stats->total_undeliverable)
                / $totalParcel
            ) * 100, 1);

// 4️⃣ Add the for-delivery-today count
        $stats->total_for_delivery_today = $totalForDeliveryToday;

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page',]),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    public function myAssignedCount(Request $request, Workspace $workspace)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['assigned' => 0, 'called' => 0]);
        }

        $assigned = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $userId)
            ->count();

        $called = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('caller_id', $userId)
            ->whereIn('status', ['CX RINGING', 'RIDER RINGING'])
            ->count();

        return response()->json(['assigned' => $assigned, 'called' => $called]);
    }
}
