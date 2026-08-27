<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Exports\RmoCallLogsExport;
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
use App\Models\CallLog;
use App\Models\Page;
use App\Models\User as SystemUser;
use App\Models\Workspace;
use App\Support\PublicWorkspaceGate;
use App\Support\RmoDailyStats;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ForDeliveryController extends Controller
{
    /**
     * Upper bound on comma-separated terms accepted by the RMO search box.
     */
    private const MAX_SEARCH_TERMS = 50;

    public function publicUpdateStatus(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        $deliveryDate = $orderForDelivery->delivery_date
            ? Carbon::parse($orderForDelivery->delivery_date)
            : null;

        // Status is editable for today's orders, and also for yesterday's
        // orders but only when the parcel was delivered.
        $isToday = $deliveryDate?->isToday() ?? false;
        $isDeliveredYesterday = ($deliveryDate?->isYesterday() ?? false)
            && (strtolower((string) $orderForDelivery->parcel_status) === 'delivered' || strtolower((string) $orderForDelivery->parcel_status) === 'returning');

        // When the workspace opts in, any past delivery date is editable —
        // not just yesterday.
        $isPastDay = $deliveryDate?->lt(today()) ?? false;
        $canEditAnyPreviousDay = $isPastDay && $this->canEditPreviousDay($workspace);

        if (! $isToday && ! $isDeliveredYesterday && ! $canEditAnyPreviousDay) {
            return redirect()->back()->with('error', "Status can only be updated for today's orders, or yesterday's delivered orders. Turn on \"Edit Previous Days\" to open up earlier dates.");
        }

        $orderForDelivery->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status updated successfully');
    }

    /**
     * Re-status every selected order in one request. Gated behind the
     * workspace's "bulk status update" switch; orders outside the editable
     * date window are skipped rather than failing the whole batch.
     */
    public function publicBulkUpdateStatus(Workspace $workspace, Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => 'required|string|max:50',
        ]);

        if (! $workspace->rmoBulkStatusUpdateEnabled()) {
            return redirect()->back()->with('error', 'Bulk status update is turned off for this workspace.');
        }

        $canEditAnyPreviousDay = $this->canEditPreviousDay($workspace);

        // Same window as the single-row update: today, plus yesterday's
        // delivered/returning parcels, widened to every past date when the
        // workspace unlocked previous-day editing.
        $editableIds = OrderForDelivery::whereIn('id', $data['ids'])
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($canEditAnyPreviousDay) {
                $query->whereDate('delivery_date', today())
                    ->orWhere(function ($q) use ($canEditAnyPreviousDay) {
                        if ($canEditAnyPreviousDay) {
                            $q->whereDate('delivery_date', '<', today());

                            return;
                        }

                        $q->whereDate('delivery_date', today()->subDay())
                            ->whereIn('parcel_status', ['delivered', 'returning']);
                    });
            })
            ->pluck('id');

        if ($editableIds->isEmpty()) {
            return redirect()->back()->with('error', "Status can only be updated for today's orders, or yesterday's delivered orders. Turn on \"Edit Previous Days\" to open up earlier dates.");
        }

        OrderForDelivery::whereIn('id', $editableIds)->update(['status' => $data['status']]);

        $skipped = count($data['ids']) - $editableIds->count();
        $message = "Updated {$editableIds->count()} order(s) to {$data['status']}.";

        if ($skipped > 0) {
            $message .= " {$skipped} order(s) skipped — outside the editable date range.";
        }

        return redirect()->back()->with('success', $message);
    }

    public function publicBulkAssign(Workspace $workspace, Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'userId' => 'required|string',
        ]);

        // Assignable for today's orders, and for yesterday's orders only when
        // the parcel was delivered or returned. Workspaces that enable
        // previous-day editing widen this to every past delivery date.
        $canEditAnyPreviousDay = $this->canEditPreviousDay($workspace);

        $updated = OrderForDelivery::whereIn('id', $request->ids)
            ->whereNull('assignee_id')
            ->where(function ($query) use ($canEditAnyPreviousDay) {
                $query->whereDate('delivery_date', today())
                    ->orWhere(function ($q) use ($canEditAnyPreviousDay) {
                        if ($canEditAnyPreviousDay) {
                            $q->whereDate('delivery_date', '<', today());

                            return;
                        }

                        $q->whereDate('delivery_date', today()->subDay())
                            ->whereIn('parcel_status', ['delivered', 'returned', 'returning']);
                    });
            })
            ->update(['assignee_id' => $request->userId]);

        return redirect()->back()->with('success', "Assigned {$updated} order(s) successfully.");
    }

    public function publicAssignUser(Workspace $workspace, $id, Request $request)
    {
        $orderForDelivery = OrderForDelivery::find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        if (! $this->canEditAssignee($orderForDelivery, $this->canEditPreviousDay($workspace))) {
            return redirect()->back()->with('error', "Assignee can only be updated for today's orders, or yesterday's delivered or returned orders. Turn on \"Edit Previous Days\" to open up earlier dates.");
        }

        if (! $request->userId) {
            return redirect()->back()->with('error', 'Please select a user before assigning.');
        }

        $orderForDelivery->update(['assignee_id' => $request->userId]);

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

        if (! $this->canEditAssignee($orderForDelivery, $this->canEditPreviousDay($workspace))) {
            return redirect()->back()->with('error', "Assignee can only be removed for today's orders, or yesterday's delivered or returned orders. Turn on \"Edit Previous Days\" to open up earlier dates.");
        }

        $orderForDelivery->update(['assignee_id' => null]);

        return redirect()->back()->with('success', 'Assignee removed successfully');
    }

    /**
     * Previous-day editing is governed solely by the workspace's rmo_settings
     * switch — the same rule for the public page and the authenticated CSR
     * routes. Only flipping the switch is permission-gated.
     */
    private function canEditPreviousDay(Workspace $workspace): bool
    {
        return $workspace->rmoEditPreviousDayEnabled();
    }

    /**
     * Assignee is editable for today's orders, and for yesterday's orders
     * only when the parcel was delivered or returned. When previous-day
     * editing is unlocked, every past delivery date is editable instead.
     */
    private function canEditAssignee(OrderForDelivery $orderForDelivery, bool $canEditPreviousDay = false): bool
    {
        $deliveryDate = $orderForDelivery->delivery_date
            ? Carbon::parse($orderForDelivery->delivery_date)
            : null;

        if ($deliveryDate?->isToday() ?? false) {
            return true;
        }

        if ($canEditPreviousDay && ($deliveryDate?->lt(today()) ?? false)) {
            return true;
        }

        return ($deliveryDate?->isYesterday() ?? false)
            && in_array(strtolower((string) $orderForDelivery->parcel_status), ['delivered', 'returned', 'returning'], true);
    }

    public function verifyPublicPassword(Request $request, Workspace $workspace)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! PublicWorkspaceGate::verify($request, $workspace, $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

        return back();
    }

    public function public(Request $request, Workspace $workspace)
    {
        // Super admins can view any RMO page and skip the public-pages password gate.
        $user = $request->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        // Gate behind the workspace's public-pages password if one is set.
        if (! $isSuperAdmin && ! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewRmoManagement)) {
            return Inertia::render('workspaces/rts/public-pages/rmo-management', [
                'workspace' => $workspace->only('id', 'name', 'slug'),
                'locked' => true,
            ]);
        }

        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $baseQuery = OrderForDelivery::where('workspace_id', $workspace->id);

        if ($request->input('assignee_id')) {
            $baseQuery->where('assignee_id', $request->input('assignee_id'));
        }

        if ($request->input('confirmee_id')) {
            $baseQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        $items = QueryBuilder::for($baseQuery)
            ->addSelect([
                'pancake_order_for_delivery.*',
                \DB::raw('(SELECT rts_rate FROM rider_delivery_summary WHERE rider_name = pancake_order_for_delivery.rider_name AND rider_phone = pancake_order_for_delivery.rider_phone LIMIT 1) as rider_rts_rate'),
            ])
            // Every caller, not just the assignee — the badge opens the modal,
            // and the modal has never filtered by CSR. Keeping the assignee
            // scoping here is what made a row read "0 calls" and then open onto
            // a full history, most often after the order was reassigned.
            ->withCount([
                'allCustomerCallLogs as customer_call_logs_count',
                'allRiderCallLogs as rider_call_logs_count',
            ])
            ->withSum('allCustomerCallLogs as customer_call_duration', 'duration')
            ->withSum('allRiderCallLogs as rider_call_duration', 'duration')
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
                    $this->applyRmoSearch($query, $value);
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
            $statsBase->where('assignee_id', $request->input('assignee_id'));
            $totalOrdersForDeliveryTodayQuery->whereHas('order', function ($orderQuery) use ($request) {
                $orderQuery->where('confirmed_by', $request->input('assignee_id'));
            });
        }

        if ($request->input('confirmee_id')) {
            $statsBase->where('conferrer_id', $request->input('confirmee_id'));
            $totalOrdersForDeliveryTodayQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        // Total uses its own base (optionally filtered via whereHas on confirmed_by)
        $totalOrdersForDeliveryToday = $totalOrdersForDeliveryTodayQuery->count();

        // The call cards report the workspace's call logs for the delivery date,
        // flat: every call that day, whether or not the number it reached
        // belongs to an order on this page and whoever placed it. Deliberately
        // not tied to the order set or to a Pancake user yet — those are
        // separate questions, and answering them here would make the figures
        // move for reasons the cards don't explain.
        //
        // One query per card. Each is independently readable and independently
        // debuggable — run any one of them on its own and it answers exactly
        // the question its card asks.
        $totalCallLogs = RmoDailyStats::callLogStat($workspace, $deliveryDate, 'COUNT(*)');

        $totalCallDuration = RmoDailyStats::callLogStat($workspace, $deliveryDate, 'COALESCE(SUM(duration), 0)');

        $connectedCallLogs = RmoDailyStats::callLogStat(
            $workspace,
            $deliveryDate,
            'COUNT(CASE WHEN duration >= '.RmoDailyStats::CONNECTED_CALL_MIN_SECONDS.' THEN 1 END)'
        );

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

        // Only list CSRs (Pancake users) tied to a shop in this workspace.
        $users = User::whereHas('shops', function ($query) use ($workspace) {
            $query->where('shops.workspace_id', $workspace->id);
        })->get();

        $workspace->load(['pages:id,name,workspace_id', 'shops:id,name,workspace_id', 'pageOwners:id,name']);

        return Inertia::render('workspaces/rts/public-pages/rmo-management', [
            'locked' => false,
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
            'total_call_logs_count' => $totalCallLogs,
            'total_call_duration' => $totalCallDuration,
            'connected_call_logs_count' => $connectedCallLogs,
            'enable_edit_previous_day' => $this->canEditPreviousDay($workspace),
            'enable_bulk_status_update' => $workspace->rmoBulkStatusUpdateEnabled(),
            'enable_auto_tag_status' => $workspace->rmoAutoTagStatusEnabled(),
        ]);
    }

    /**
     * The RMO list as the page currently has it filtered: delivery date, the
     * "mine only" assignee/confirmee toggles, and the filter bar.
     *
     * Shared by the exports so a downloaded file always covers exactly the rows
     * on screen — the two can't drift apart.
     */
    private function filteredRmoQuery(Request $request, Workspace $workspace, string $deliveryDate): QueryBuilder
    {
        $baseQuery = OrderForDelivery::where('workspace_id', $workspace->id);

        if ($request->input('assignee_id')) {
            $baseQuery->where('assignee_id', $request->input('assignee_id'));
        }

        if ($request->input('confirmee_id')) {
            $baseQuery->where('conferrer_id', $request->input('confirmee_id'));
        }

        return QueryBuilder::for($baseQuery)
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
                    $this->applyRmoSearch($query, $value);
                }),
            ])
            ->whereDate('delivery_date', $deliveryDate);
    }

    public function publicExport(Request $request, Workspace $workspace)
    {
        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $query = $this->filteredRmoQuery($request, $workspace, $deliveryDate)
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
            ]);

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }

        $filename = 'rmo-management-'.$deliveryDate.'-'.now()->format('His').'.xlsx';

        return Excel::download(new RmoManagementExport($query, $columns), $filename);
    }

    /**
     * RMO management search accepts several terms at once, separated by commas
     * (e.g. pasting three tracking codes). Each term is matched against every
     * searchable field and the terms are OR'd together, so "ABC, DEF" returns
     * rows matching either. Spatie's QueryBuilder already explodes a
     * comma-delimited filter value into an array, so handle both shapes.
     *
     * The value is `mixed` on purpose: Spatie coerces a bare "true"/"false"
     * search term into a boolean before it reaches us.
     *
     * @param  string|array<int, string>|bool|null  $value
     */
    private function applyRmoSearch(Builder $query, mixed $value): void
    {
        $terms = collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            // Guardrail: each term adds three LIKE subqueries, so cap the fan-out.
            ->take(self::MAX_SEARCH_TERMS)
            ->values();

        if ($terms->isEmpty()) {
            return;
        }

        $query->where(function ($outer) use ($terms) {
            foreach ($terms as $term) {
                $outer->orWhere(function ($q) use ($term) {
                    $q->whereHas('order', function ($orderQuery) use ($term) {
                        $orderQuery->where('order_number', 'LIKE', "%{$term}%")
                            ->orWhere('tracking_code', 'LIKE', "%{$term}%")
                            ->orWhereHas('shippingAddress', function ($addrQuery) use ($term) {
                                $addrQuery->where('full_name', 'LIKE', "%{$term}%");
                            });
                    })
                        ->orWhere('rider_name', 'LIKE', "%{$term}%")
                        ->orWhereHas('conferrer', function ($conferrerQuery) use ($term) {
                            $conferrerQuery->where('name', 'LIKE', "%{$term}%");
                        });
                });
            }
        });
    }

    public function myAssignedCount(Request $request, Workspace $workspace)
    {
        $userId = $request->query('user_id');

        if (! $userId) {
            return response()->json(['total' => 0, 'called' => 0, 'delivered' => 0, 'returning' => 0]);
        }

        $row = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $userId)
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

    /**
     * Every call log behind the RMO list for the selected delivery date, as one
     * row per call, honouring the page's current filters.
     *
     * The per-order modal answers "who called this customer?"; this answers the
     * same question for the whole filtered day in one file. A call belongs to an
     * order when the CSR, date and phone all line up — the same rule the
     * customer/rider call-log relations use for their on-screen counts — so a
     * number shared by two orders is reported under both.
     */
    public function publicExportCallLogs(Request $request, Workspace $workspace)
    {
        // The page itself is behind the public password gate, so the bulk
        // download is too — except for signed-in members of the workspace, who
        // reach the same data through the authenticated CSR route.
        $user = $request->user();
        $isMember = $user && ($user->isSuperAdmin() || $user->isMemberOf($workspace));

        if (! $isMember && ! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewRmoManagement)) {
            abort(403);
        }

        $deliveryDate = $request->input('delivery_date') ?: now()->toDateString();

        $query = $this->filteredRmoQuery($request, $workspace, $deliveryDate)
            ->with(['order:id,order_number,tracking_code', 'assignee:id,name', 'conferrer:id,name']);

        $filename = 'rmo-call-logs-'.$deliveryDate.'-'.now()->format('His').'.xlsx';

        return Excel::download(new RmoCallLogsExport($query, $workspace->id, $deliveryDate), $filename);
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
            ->get(['id', 'user_id', 'assignee_user_id', 'phone_number', 'type', 'duration', 'call_date', 'call_time']);

        return response()->json($this->namedCallers($logs));
    }

    /**
     * Stamp each call with the name of whoever actually placed it.
     *
     * A log carries whichever id the app that synced it knew about: the older
     * mobile build sends a Pancake user id in `user_id`, the newer one sends the
     * system user id in `assignee_user_id`. Resolving both here is what lets the
     * modal name the real caller — the list isn't filtered by CSR, so two people
     * who rang the same number on the same day both show up, and the page has no
     * way to tell them apart on its own.
     *
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<int, CallLog>
     */
    private function namedCallers(Collection $logs): Collection
    {
        // Two lookups for the whole modal, however many calls it lists.
        $systemNames = SystemUser::whereIn('id', $logs->pluck('assignee_user_id')->filter()->unique())
            ->pluck('name', 'id');

        $pancakeNames = User::whereIn('id', $logs->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');

        return $logs->each(function (CallLog $log) use ($systemNames, $pancakeNames) {
            // Left null rather than guessed at when neither id resolves — an
            // empty cell is honest, a wrong name isn't.
            $log->setAttribute('called_by', $systemNames->get($log->assignee_user_id)
                ?? $pancakeNames->get($log->user_id));
        });
    }
}
