<?php

namespace Modules\Pancake\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Pancake\Jobs\ImportOrderShippingFees;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Support\CustomerRtsRisk;
use Modules\Pancake\Support\ShippingFeeImportStatus;
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
     * Date columns the range filter may be pointed at.
     *
     * An allowlist because the chosen key goes straight into a whereDate(); the
     * request never names a column, it names one of these keys.
     *
     * inserted_at is Pancake's own creation time and stays the default — it is
     * what the list has always filtered and sorted on. created_at is this app's
     * row, i.e. when the sync first saw the order, which is a different question.
     */
    public const DATE_FIELDS = [
        'inserted_at',
        'confirmed_at',
        'created_at',
        'shipped_at',
        'delivered_at',
        'returning_at',
        'returned_at',
        'updated_at',
    ];

    /** The column the date range applies to, falling back to Pancake's insert time. */
    private function dateColumn(Request $request): string
    {
        $type = (string) $request->input('filter.date_type');

        return in_array($type, self::DATE_FIELDS, true)
            ? 'pancake_orders.'.$type
            : 'pancake_orders.inserted_at';
    }

    /**
     * Limit to orders delivered by a given rider. Mirrors RtsRiderQuery: the rider
     * is the rider_name on the latest "On Delivery" parcel journey for the order,
     * so this matches exactly the set counted in the RTS "By Rider" breakdown.
     */
    private function applyRider($query, string $rider)
    {
        return $query->whereHas('parcelJourneys', function ($q) use ($rider) {
            $q->where('rider_name', $rider);
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

        $dateColumn = $this->dateColumn($request);

        $orders = QueryBuilder::for(clone $base)
            ->with([
                'shippingAddress:id,order_id,full_name,phone_number,full_address',
                'items:id,order_id,name,quantity',
                'tags:id,order_id,name',
            ])
            // Explicit, because selecting the customer's return rate alongside
            // would otherwise drop the table's own columns from the select.
            ->select('pancake_orders.*')
            ->selectRaw(CustomerRtsRisk::rateSql().' as cx_rts_rate')
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $this->applySearch($q, $v)),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate($dateColumn, '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate($dateColumn, '<=', $v)),
                // Consumed by dateColumn() above rather than as a filter of its own —
                // declared so Spatie doesn't reject the request for carrying it.
                AllowedFilter::callback('date_type', fn () => null),
                // Read the raw request value, not Spatie's — it splits on commas,
                // which would break rider names that legitimately contain one.
                AllowedFilter::callback('rider', fn ($q) => $this->applyRider($q, (string) $request->input('filter.rider'))),
                // Accepts one status or a comma-separated list (e.g. returning,returned).
                AllowedFilter::callback('status', fn ($q, $v) => $q->whereIn(
                    'pancake_orders.status_name',
                    is_array($v) ? $v : explode(',', $v),
                )),
            ])
            ->allowedSorts(['order_number', 'total_amount', 'inserted_at', 'updated_at', 'confirmed_at', 'status_name', 'cx_rts_rate'])
            ->defaultSort('-inserted_at')
            ->paginate((int) $request->input('per_page', 50))
            ->withQueryString();

        // Banded here rather than in the select so the thresholds live in one
        // readable place; the rate itself still comes from the query, so sorting
        // on the column and reading the badge agree.
        $orders->getCollection()->each(fn (Order $order) => $order->setAttribute(
            'cx_rts_level',
            CustomerRtsRisk::level($order->cx_rts_rate === null ? null : (float) $order->cx_rts_rate),
        ));

        // Per-status counts for the tab bar: search/date applied directly (not via
        // QueryBuilder, which would reject the `status` filter the request carries),
        // and status itself is intentionally ignored so each tab shows its total.
        $filter = (array) $request->input('filter', []);
        $statusCounts = (clone $base)
            ->when(($filter['search'] ?? null), fn ($q, $v) => $this->applySearch($q, $v))
            ->when(($filter['date_from'] ?? null), fn ($q, $v) => $q->whereDate($dateColumn, '>=', $v))
            ->when(($filter['date_to'] ?? null), fn ($q, $v) => $q->whereDate($dateColumn, '<=', $v))
            ->when(($filter['rider'] ?? null), fn ($q, $v) => $this->applyRider($q, (string) $v))
            ->selectRaw('status_name, COUNT(*) as total')
            ->groupBy('status_name')
            ->pluck('total', 'status_name');

        return Inertia::render('workspaces/pancake/orders/index', [
            'workspace' => $workspace,
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'totalCount' => (int) $statusCounts->sum(),
            'shippingFeeImport' => ShippingFeeImportStatus::get($workspace->id),
            'dateFields' => self::DATE_FIELDS,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Queue a courier billing export for import: its shipping cost is written
     * onto the orders whose tracking code matches the sheet's waybill number.
     */
    public function importShippingFees(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ImportOrderShippingFees->value, $workspace);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200'],
        ]);

        // One at a time per workspace: two sheets writing the same column would
        // race, and the page only reports on one import.
        if (ShippingFeeImportStatus::running($workspace->id)) {
            throw ValidationException::withMessages([
                'file' => 'A shipping fee import is already running for this workspace. Wait for it to finish.',
            ]);
        }

        $file = $request->file('file');
        $name = $file->getClientOriginalName();
        $path = $file->store('imports/shipping-fees', 'local');

        ShippingFeeImportStatus::begin($workspace->id, $name);

        ImportOrderShippingFees::dispatch($workspace->id, $path, $name);

        return redirect()->back()->with('success', "Queued {$name} — shipping fees will land on the orders shortly.");
    }

    /** Progress of the queued import, polled by the orders page. */
    public function shippingFeeImportStatus(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewOrders->value, $workspace);

        return response()->json([
            'import' => ShippingFeeImportStatus::get($workspace->id),
        ]);
    }
}
