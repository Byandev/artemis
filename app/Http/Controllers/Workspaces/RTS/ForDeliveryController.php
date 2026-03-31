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
                        ->selectRaw("
                            id, order_number, status_name, final_amount, parcel_status, tracking_code, delivery_attempts,
                            (
                                SELECT SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                                FROM pancake_order_phone_number_reports
                                WHERE order_id = pancake_orders.id
                                and pancake_order_phone_number_reports.type = 'latest'
                            ) AS cx_rts_rate
                        ")

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
                AllowedFilter::callback('shop_id', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('shop_id', $values);
                }),
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
            ->whereDate('delivery_date', now())
            ->paginate(10);

        // Build a base query for stats that respects page/shop filters
        $statsBase = OrderForDelivery::where('workspace_id', $workspace->id)
        ->where('delivery_date',  now());

        $filterPageIds = $request->input('filter.page_id');
        if ($filterPageIds) {
            $pageIds = is_string($filterPageIds) ? explode(',', $filterPageIds) : (array) $filterPageIds;
            $statsBase->whereIn('page_id', $pageIds);
        }

        $filterShopId = $request->input('filter.shop_id');
        if ($filterShopId) {
            $shopIds = is_string($filterShopId) ? explode(',', $filterShopId) : (array) $filterShopId;
            $statsBase->whereIn('shop_id', $shopIds);
        }

        // 1️⃣ Total orders
        $totalOrders = (clone $statsBase)->count();

        // 2️⃣ Total for delivery today
        $totalForDeliveryToday = (clone $statsBase)
            ->whereHas('order', function ($query) {
                $query->where('parcel_status', 'out_for_delivery')
                    ->whereDate('created_at', now());
            })
            ->count();

        // 3️⃣ Called rate (not pending)
        $totalCalled = (clone $statsBase)
            ->where('status', '!=', 'PENDING')
            ->count();

        $calledRate = $totalOrders > 0 ? round(($totalCalled / $totalOrders) * 100, 1) : 0;

        // 4️⃣ Successful rate (parcel delivered)
        $totalParcel = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->whereNotNull('parcel_status'))
            ->count();

        $totalDelivered = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->where('parcel_status', 'delivered'))
            ->count();

        $successfulRate = $totalParcel > 0 ? round(($totalDelivered / $totalParcel) * 100, 1) : 0;

        // 5️⃣ Unsuccessful rate (problematic + returning + undeliverable)
        $totalUnsuccessful = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->whereIn('parcel_status', ['problematic', 'returning', 'undeliverable']))
            ->count();

        $unsuccessfulRate = $totalParcel > 0 ? round(($totalUnsuccessful / $totalParcel) * 100, 1) : 0;

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id']);

        return Inertia::render('workspaces/rts/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'total_for_delivery_today' => $totalForDeliveryToday,
            'called_rate' => $calledRate,
            'successful_rate' => $successfulRate,
            'unsuccessful_rate' => $unsuccessfulRate,
        ]);
    }

    public function updateStatus(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::where('order_id', $id)->first();

        if (!$orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        $data = ['status' => $request->status];

        if ($request->has('removeAssignee') && $request->removeAssignee) {
            $data['assignee_id'] = null;
        } else {
            $data['assignee_id'] = auth()->id();
        }

        $orderForDelivery->update($data);

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
                        ->selectRaw("
                            id, order_number, status_name, final_amount, parcel_status, tracking_code, delivery_attempts,
                            (
                                SELECT SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0)
                                FROM pancake_order_phone_number_reports
                                WHERE order_id = pancake_orders.id
                                and pancake_order_phone_number_reports.type = 'latest'
                            ) AS cx_rts_rate
                        ")
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
                AllowedFilter::callback('shop_id', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('shop_id', $values);
                }),
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
            ->whereDate('delivery_date', now())
            ->paginate(10);

        // Build a base query for stats that respects page/shop filters
        $statsBase = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('delivery_date', now());

        $filterPageIds = $request->input('filter.page_id');
        if ($filterPageIds) {
            $pageIds = is_string($filterPageIds) ? explode(',', $filterPageIds) : (array) $filterPageIds;
            $statsBase->whereIn('page_id', $pageIds);
        }

        $filterShopId = $request->input('filter.shop_id');
        if ($filterShopId) {
            $shopIds = is_string($filterShopId) ? explode(',', $filterShopId) : (array) $filterShopId;
            $statsBase->whereIn('shop_id', $shopIds);
        }

        // 1️⃣ Total orders
        $totalOrders = (clone $statsBase)->count();

        // 2️⃣ Total for delivery today
        $totalForDeliveryToday = (clone $statsBase)
            ->whereHas('order', function ($query) {
                $query->where('parcel_status', 'out_for_delivery')
                    ->whereDate('created_at', now());
            })
            ->count();

        // 3️⃣ Called rate (not pending)
        $totalCalled = (clone $statsBase)
            ->where('status', '!=', 'PENDING')
            ->count();

        $calledRate = $totalOrders > 0 ? round(($totalCalled / $totalOrders) * 100, 1) : 0;

        // 4️⃣ Successful rate (parcel delivered)
        $totalParcel = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->whereNotNull('parcel_status'))
            ->count();

        $totalDelivered = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->where('parcel_status', 'delivered'))
            ->count();

        $successfulRate = $totalParcel > 0 ? round(($totalDelivered / $totalParcel) * 100, 1) : 0;

        // 5️⃣ Unsuccessful rate (problematic + returning + undeliverable)
        $totalUnsuccessful = (clone $statsBase)
            ->whereHas('order', fn($q) => $q->whereIn('parcel_status', ['problematic', 'returning', 'undeliverable']))
            ->count();

        $unsuccessfulRate = $totalParcel > 0 ? round(($totalUnsuccessful / $totalParcel) * 100, 1) : 0;

        $users = User::get(['id', 'name']);

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id']);



        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
            'total_for_delivery_today' => $totalForDeliveryToday,
            'called_rate' => $calledRate,
            'successful_rate' => $successfulRate,
            'unsuccessful_rate' => $unsuccessfulRate,
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
