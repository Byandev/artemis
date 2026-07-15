<?php

namespace Modules\Pancake\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    use AuthorizesRequests;

    private function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    /** Search across order number, tracking code, and the shipping address. */
    private function applySearch($query, string $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('pancake_orders.order_number', 'like', "%{$value}%")
                ->orWhere('pancake_orders.tracking_code', 'like', "%{$value}%")
                ->orWhereHas('shippingAddress', fn ($sa) => $sa
                    ->where('full_name', 'like', "%{$value}%")
                    ->orWhere('phone_number', 'like', "%{$value}%")
                    ->orWhere('full_address', 'like', "%{$value}%"));
        });
    }

    /**
     * Limit to orders delivered by a given rider. Mirrors RtsRiderQuery: the rider
     * is the rider_name on the latest "On Delivery" parcel journey for the order,
     * so this matches exactly the set counted in the RTS "By Rider" breakdown.
     */
    private function applyRider($query, string $rider)
    {
        return $query->whereIn('pancake_orders.id', function ($sub) use ($rider) {
            $sub->from('parcel_journeys')
                ->select('order_id')
                ->where('rider_name', $rider)
                ->whereIn('id', function ($latest) {
                    $latest->from('parcel_journeys')
                        ->selectRaw('MAX(id)')
                        ->where('status', 'On Delivery')
                        ->groupBy('order_id');
                });
        });
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewOrders->value, $workspace);

        // Base query: workspace-scoped, honouring per-user team visibility.
        $base = Order::ofWorkspace($workspace)
            ->when(
                TeamVisibility::shouldScope($request->user(), $workspace),
                fn ($q) => $q->visibleTo($request->user(), $workspace),
            );

        $orders = QueryBuilder::for(clone $base)
            ->with([
                'shippingAddress:id,order_id,full_name,phone_number,full_address',
                'items:id,order_id,name,quantity',
                'tags:id,order_id,name',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $this->applySearch($q, $v)),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('pancake_orders.inserted_at', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('pancake_orders.inserted_at', '<=', $v)),
                // Read the raw request value, not Spatie's — it splits on commas,
                // which would break rider names that legitimately contain one.
                AllowedFilter::callback('rider', fn ($q) => $this->applyRider($q, (string) $request->input('filter.rider'))),
                // Accepts one status or a comma-separated list (e.g. returning,returned).
                AllowedFilter::callback('status', fn ($q, $v) => $q->whereIn(
                    'pancake_orders.status_name',
                    is_array($v) ? $v : explode(',', $v),
                )),
            ])
            ->allowedSorts(['order_number', 'total_amount', 'inserted_at', 'updated_at', 'confirmed_at', 'status_name'])
            ->defaultSort('-inserted_at')
            ->paginate((int) $request->input('per_page', 50))
            ->withQueryString();

        // Per-status counts for the tab bar: search/date applied directly (not via
        // QueryBuilder, which would reject the `status` filter the request carries),
        // and status itself is intentionally ignored so each tab shows its total.
        $filter = (array) $request->input('filter', []);
        $statusCounts = (clone $base)
            ->when(($filter['search'] ?? null), fn ($q, $v) => $this->applySearch($q, $v))
            ->when(($filter['date_from'] ?? null), fn ($q, $v) => $q->whereDate('pancake_orders.inserted_at', '>=', $v))
            ->when(($filter['date_to'] ?? null), fn ($q, $v) => $q->whereDate('pancake_orders.inserted_at', '<=', $v))
            ->when(($filter['rider'] ?? null), fn ($q, $v) => $this->applyRider($q, (string) $v))
            ->selectRaw('status_name, COUNT(*) as total')
            ->groupBy('status_name')
            ->pluck('total', 'status_name');

        return Inertia::render('workspaces/pancake/orders/index', [
            'workspace' => $workspace,
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'totalCount' => (int) $statusCounts->sum(),
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
