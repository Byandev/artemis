<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Exports\RmoManagementExport;
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
use App\Models\CallLog;
use App\Models\Page;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ForDeliveryNewController extends Controller
{
    public function publicUpdateStatus(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        if (! $orderForDelivery->delivery_date || ! Carbon::parse($orderForDelivery->delivery_date)->isToday()) {
            return redirect()->back()->with('error', 'Status can only be updated for orders scheduled for delivery today.');
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

        if (! $orderForDelivery->delivery_date || ! Carbon::parse($orderForDelivery->delivery_date)->isToday()) {
            return redirect()->back()->with('error', 'Assignee can only be updated for orders scheduled for delivery today.');
        }

        if (! $request->userId) {
            return redirect()->back()->with('error', 'Please select a user before assigning.');
        }

        $orderForDelivery->update($this->assigneePayload($request, $workspace, $request->userId));

        return redirect()->back()->with('success', 'Assignee updated successfully');
    }

    public function publicUpdatePhones(Workspace $workspace, $id, Request $request)
    {
        if (app()->environment('production')) {
            return redirect()->back()->with('error', 'Editing phone numbers is disabled in production.');
        }

        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        $data = $request->validate([
            'customer_phone' => ['nullable', 'string'],
            'rider_phone' => ['nullable', 'string'],
        ]);

        $orderForDelivery->update($data);

        return redirect()->back()->with('success', 'Phone numbers updated successfully');
    }

    public function publicRemoveAssignee(Workspace $workspace, $id)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        if (! $orderForDelivery->delivery_date || ! Carbon::parse($orderForDelivery->delivery_date)->isToday()) {
            return redirect()->back()->with('error', 'Assignee can only be removed for orders scheduled for delivery today.');
        }

        $orderForDelivery->update([
            'assignee_id' => null,
            'assignee_user_id' => null,
        ]);

        return redirect()->back()->with('success', 'Assignee removed successfully');
    }

    public function public(Request $request, Workspace $workspace)
    {
        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $baseQuery = OrderForDelivery::where('workspace_id', $workspace->id);

        $this->applyAssigneeFilter($baseQuery, $request);

        if ($request->input('confirmee_id')) {
            $baseQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        $items = QueryBuilder::for($baseQuery)
            ->addSelect([
                'pancake_order_for_delivery.*',
                \DB::raw('(SELECT rts_rate FROM rider_delivery_summary WHERE rider_name = pancake_order_for_delivery.rider_name AND rider_phone = pancake_order_for_delivery.rider_phone LIMIT 1) as rider_rts_rate'),
            ])
            ->withCount(['customerCallLogsByAssignee as customer_call_logs_count', 'riderCallLogsByAssignee as rider_call_logs_count'])
            ->withSum('customerCallLogsByAssignee as customer_call_duration', 'duration')
            ->withSum('riderCallLogsByAssignee as rider_call_duration', 'duration')
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
                'pancakeAssignee' => function ($query) {
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
                    $query->whereIn('parcel_status', $values);
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
                AllowedSort::custom('cx_rts_rate', new CxRtsRateSort),
            ])
            ->whereDate('delivery_date', $deliveryDate)
            ->paginate($request->input('per_page', 100));

        // Build a base query for stats that respects page/shop/assignee filters
        $statsBase = OrderForDelivery::where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $deliveryDate);

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

        $filterUserId = $request->input('filter.user_id');
        if ($filterUserId) {
            $userIds = is_string($filterUserId) ? explode(',', $filterUserId) : (array) $filterUserId;
            $ownerPageIds = Page::whereIn('owner_id', $userIds)
                ->where('workspace_id', $workspace->id)
                ->pluck('id');
            $statsBase->whereIn('page_id', $ownerPageIds);
        }

        $totalOrdersForDeliveryTodayQuery = (clone $statsBase);

        if ($request->input('assignee_id')) {
            $this->applyAssigneeFilter($statsBase, $request);
            $this->applyAssigneeFilter($totalOrdersForDeliveryTodayQuery, $request);
        }

        if ($request->input('confirmee_id')) {
            $statsBase->where('conferrer_id', $request->input('confirmee_id'));
            $totalOrdersForDeliveryTodayQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        // Total uses its own base (optionally filtered via whereHas on confirmed_by)
        $totalOrdersForDeliveryToday = $totalOrdersForDeliveryTodayQuery->count();

        // The other 4 stats share $statsBase — roll them into a single aggregate query
        $statusBreakdown = $statsBase
            ->selectRaw("
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called,
                SUM(CASE WHEN parcel_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN parcel_status = 'returning' THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN parcel_status = 'undeliverable' THEN 1 ELSE 0 END) as problematic
            ")
            ->first();

        $totalCalled = (int) ($statusBreakdown->called ?? 0);
        $totalDelivered = (int) ($statusBreakdown->delivered ?? 0);
        $totalReturning = (int) ($statusBreakdown->returning_count ?? 0);
        $totalProblematic = (int) ($statusBreakdown->problematic ?? 0);

        $items->getCollection()->each(function ($item) {
            $item->setRelation('assignee', $item->pancakeAssignee ?? $item->assignee);
        });

        $users = User::get();

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id', 'pageOwners:id,name']);

        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
                'delivery_date' => $deliveryDate,
            ],
            'users' => $users,
            'total_for_delivery_today' => $totalOrdersForDeliveryToday,
            'called_count' => $totalCalled,
            'delivered_count' => $totalDelivered,
            'returning_count' => $totalReturning,
            'problematic_count' => $totalProblematic,
        ]);
    }

    public function csrRmoManagement(Request $request, Workspace $workspace)
    {
        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $baseQuery = OrderForDelivery::where('workspace_id', $workspace->id);

        $this->applyAssigneeFilter($baseQuery, $request);

        if ($request->input('confirmee_id')) {
            $baseQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        $items = QueryBuilder::for($baseQuery)
            ->addSelect([
                'pancake_order_for_delivery.*',
                \DB::raw('(SELECT rts_rate FROM rider_delivery_summary WHERE rider_name = pancake_order_for_delivery.rider_name AND rider_phone = pancake_order_for_delivery.rider_phone LIMIT 1) as rider_rts_rate'),
            ])
            ->withCount(['customerCallLogsByAssignee as customer_call_logs_count', 'riderCallLogsByAssignee as rider_call_logs_count'])
            ->withSum('customerCallLogsByAssignee as customer_call_duration', 'duration')
            ->withSum('riderCallLogsByAssignee as rider_call_duration', 'duration')
            ->withCount([
                'customerCallLogsByAssignee as my_customer_call_count' => fn ($q) => $q->where('call_logs.assignee_user_id', auth()->id()),
                'riderCallLogsByAssignee as my_rider_call_count' => fn ($q) => $q->where('call_logs.assignee_user_id', auth()->id()),
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
                    $query->whereIn('parcel_status', $values);
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
                AllowedSort::custom('cx_rts_rate', new CxRtsRateSort),
            ])
            ->whereDate('delivery_date', $deliveryDate)
            ->paginate($request->input('per_page', 100));

        $statsBase = OrderForDelivery::where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $deliveryDate);

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

        $filterUserId = $request->input('filter.user_id');
        if ($filterUserId) {
            $userIds = is_string($filterUserId) ? explode(',', $filterUserId) : (array) $filterUserId;
            $ownerPageIds = Page::whereIn('owner_id', $userIds)
                ->where('workspace_id', $workspace->id)
                ->pluck('id');
            $statsBase->whereIn('page_id', $ownerPageIds);
        }

        $totalOrdersForDeliveryTodayQuery = (clone $statsBase);

        if ($request->input('assignee_id')) {
            $this->applyAssigneeFilter($statsBase, $request);
            $this->applyAssigneeFilter($totalOrdersForDeliveryTodayQuery, $request);
        }

        if ($request->input('confirmee_id')) {
            $statsBase->where('conferrer_id', $request->input('confirmee_id'));
            $totalOrdersForDeliveryTodayQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        $totalOrdersForDeliveryToday = $totalOrdersForDeliveryTodayQuery->count();

        $statusBreakdown = $statsBase
            ->selectRaw("
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called,
                SUM(CASE WHEN parcel_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN parcel_status = 'returning' THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN parcel_status = 'undeliverable' THEN 1 ELSE 0 END) as problematic
            ")
            ->first();

        $users = User::get();

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id', 'pageOwners:id,name']);

        return Inertia::render('workspaces/csr/rmo-management', [
            'orders' => $items,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
                'delivery_date' => $deliveryDate,
            ],
            'users' => $users,
            'total_for_delivery_today' => $totalOrdersForDeliveryToday,
            'called_count' => (int) ($statusBreakdown->called ?? 0),
            'delivered_count' => (int) ($statusBreakdown->delivered ?? 0),
            'returning_count' => (int) ($statusBreakdown->returning_count ?? 0),
            'problematic_count' => (int) ($statusBreakdown->problematic ?? 0),
        ]);
    }

    public function publicExport(Request $request, Workspace $workspace)
    {
        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $baseQuery = OrderForDelivery::where('workspace_id', $workspace->id);

        $this->applyAssigneeFilter($baseQuery, $request);

        if ($request->input('confirmee_id')) {
            $baseQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        $query = QueryBuilder::for($baseQuery)
            ->addSelect([
                'pancake_order_for_delivery.*',
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
                        ]);
                },
                'conferrer:id,name',
                'assignee:id,name',
                'pancakeAssignee:id,name',
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
                    $query->whereIn('parcel_status', $values);
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
            ->whereDate('delivery_date', $deliveryDate);

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }

        $filename = 'rmo-management-'.$deliveryDate.'-'.now()->format('His').'.xlsx';

        return Excel::download(
            new RmoManagementExport($query, $columns, $this->isPublicRmoRequest($request)),
            $filename
        );
    }

    public function myAssignedCount(Request $request, Workspace $workspace)
    {
        $userId = $request->query('user_id');

        if (! $userId) {
            return response()->json(['total' => 0, 'called' => 0, 'delivered' => 0, 'returning' => 0]);
        }

        $row = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $userId)
            ->whereDate('delivery_date', now())
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called,
                SUM(CASE WHEN parcel_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN parcel_status = 'returning' THEN 1 ELSE 0 END) as returning_count
            ")
            ->first();

        return response()->json([
            'total' => (int) ($row->total ?? 0),
            'called' => (int) ($row->called ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'returning' => (int) ($row->returning_count ?? 0),
        ]);
    }

    public function callLogs(Workspace $workspace, Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'date' => ['required', 'date'],
        ]);

        $logs = CallLog::where('workspace_id', $workspace->id)
            ->where('phone_number', $request->input('phone_number'))
            ->whereDate('call_date', $request->input('date'))
            ->orderBy('call_time', 'desc')
            ->get(['id', 'user_id', 'phone_number', 'type', 'duration', 'call_date', 'call_time']);

        return response()->json($logs);
    }

    private function isPublicRmoRequest(Request $request): bool
    {
        return $request->routeIs('public-page.rmo-management.*')
            || $request->routeIs('public-page.rmo-management');
    }

    private function applyAssigneeFilter($query, Request $request): void
    {
        if (! $request->input('assignee_id')) {
            return;
        }

        $query->where(
            $this->isPublicRmoRequest($request) ? 'assignee_id' : 'assignee_user_id',
            $request->input('assignee_id')
        );
    }

    private function assigneePayload(Request $request, Workspace $workspace, int|string $userId): array
    {
        if ($this->isPublicRmoRequest($request)) {
            $pancakeUser = User::whereKey($userId)
                ->whereHas('shops', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->first();

            return [
                'assignee_id' => $userId,
                'assignee_user_id' => $pancakeUser?->user_id,
            ];
        }

        $pancakeUserId = User::where('user_id', $userId)
            ->whereHas('shops', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->value('id');

        return [
            'assignee_id' => $pancakeUserId,
            'assignee_user_id' => $userId,
        ];
    }
}
