<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Http\Sorts\Order\ForDelivery\ConferrerNameSort;
use App\Http\Sorts\Order\ForDelivery\CustomerNameSort;
use App\Http\Sorts\Order\ForDelivery\CxRtsRateSort;
use App\Http\Sorts\Order\ForDelivery\LocationRtsRateSort;
use App\Http\Sorts\Order\ForDelivery\OrderAmountSort;
use App\Http\Sorts\Order\ForDelivery\OrderDeliveryAttemptSort;
use App\Http\Sorts\Order\ForDelivery\OrderNumberSort;
use App\Http\Sorts\Order\ForDelivery\OrderParcelStatusSort;
use App\Http\Sorts\Order\ForDelivery\OrderTrackingCodeSort;
use App\Http\Sorts\Order\ForDelivery\RiderRtsSort;
use App\Http\Sorts\Order\ForDelivery\RiskScoreSort;
use App\Models\Page;
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
    public function publicUpdateStatus(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        $orderForDelivery->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status updated successfully');
    }

    public function publicAssignUser(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        if (! $request->userId) {
            return redirect()->back()->with('error', 'Please select a user before assigning.');
        }

        $orderForDelivery->update(['assignee_id' => $request->userId]);

        return redirect()->back()->with('success', 'Assignee updated successfully' . $request->userId);
    }

    public function publicRemoveAssignee(Workspace $workspace, $id)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        $orderForDelivery->update(['assignee_id' => null]);

        return redirect()->back()->with('success', 'Assignee removed successfully');
    }

    public function public(Request $request, Workspace $workspace)
    {
        $items = QueryBuilder::for(OrderForDelivery::class)
            ->where('workspace_id', $workspace->id)
            ->addSelect([
                'pancake_order_for_delivery.*',
                \DB::raw('(SELECT rts_rate FROM rider_delivery_summary WHERE rider_name = pancake_order_for_delivery.rider_name AND rider_phone = pancake_order_for_delivery.rider_phone LIMIT 1) as rider_rts_rate'),
                \DB::raw('('.RiskScoreSort::sql().') as risk_score'),
            ])
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
                AllowedFilter::callback('user_id', function ($query, $value) use ($workspace) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $pageIds = Page::whereIn('owner_id', $values)
                        ->where('workspace_id', $workspace->id)
                        ->pluck('id');
                    $query->whereIn('page_id', $pageIds);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->whereHas('order', function ($orderQuery) use ($value) {
                            $orderQuery->where('order_number', 'LIKE', "%{$value}%")
                                ->orWhere('tracking_code', 'LIKE', "%{$value}%")
                                ->orWhereHas('shippingAddress', function ($addrQuery) use ($value) {
                                    $addrQuery->where('full_name', 'LIKE', "%{$value}%");
                                });
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
                AllowedSort::custom('rider_rts_rate', new RiderRtsSort),
                AllowedSort::custom('risk_score', new RiskScoreSort),
                AllowedSort::custom('cx_rts_rate', new CxRtsRateSort),
            ])
            ->whereDate('delivery_date', now())
            ->paginate($request->input('per_page', 100));

        // Build a base query for stats that respects page/shop filters
        $statsBase = OrderForDelivery::where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', now());

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
        $totalOrdersForDeliveryToday = (clone $statsBase)->count();

        // 3️⃣ Called rate (not pending)
        $totalCalled = (clone $statsBase)
            ->where('status', '!=', 'PENDING')
            ->count();

        $calledRate = $totalOrdersForDeliveryToday > 0 ? round(($totalCalled / $totalOrdersForDeliveryToday) * 100, 1) : 0;

        $totalDelivered = (clone $statsBase)
            ->whereHas('order', fn ($q) => $q->where('parcel_status', 'delivered'))
            ->count();

        $successfulRate = $totalOrdersForDeliveryToday > 0 ? round(($totalDelivered / $totalOrdersForDeliveryToday) * 100, 1) : 0;

        // 5️⃣ Unsuccessful rate (problematic + returning + undeliverable)
        $totalUnsuccessful = (clone $statsBase)
            ->whereHas('order', fn ($q) => $q->whereIn('parcel_status', ['problematic', 'returning', 'undeliverable']))
            ->count();

        $unsuccessfulRate = $totalOrdersForDeliveryToday > 0 ? round(($totalUnsuccessful / $totalOrdersForDeliveryToday) * 100, 1) : 0;

        $users = User::get(['id', 'name']);

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id', 'pageOwners:id,name']);

        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
            'total_for_delivery_today' => $totalOrdersForDeliveryToday,
            'called_rate' => $calledRate,
            'successful_rate' => $successfulRate,
            'unsuccessful_rate' => $unsuccessfulRate,
        ]);
    }

    public function myAssignedCount(Request $request, Workspace $workspace)
    {
        $userId = $request->query('user_id');

        if (! $userId) {
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
