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
     * Every one of them is a Pancake lifecycle stamp, inserted_at being the
     * default — it is what the list has always filtered and sorted on. This
     * app's own created_at / updated_at are deliberately absent: they record
     * when the sync last touched our row, which is a question about the sync
     * rather than about the order.
     */
    public const DATE_FIELDS = [
        'inserted_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'returning_at',
        'returned_at',
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
     * Single-number comparisons on the customer's return rate, mapped to SQL.
     *
     * An allowlist because the chosen key is interpolated into the comparison;
     * the request names a key, never an operator. `between` is absent because it
     * reads a second number and is built by hand below.
     */
    public const RTS_OPERATORS = [
        'gt' => '>',
        'lt' => '<',
        'eq' => '=',
    ];

    /** Every comparison the page may offer, in the order it lists them. */
    public const RTS_COMPARISONS = ['gt', 'lt', 'eq', 'between'];

    /**
     * Narrow by the customer's return report — the same rate the Customer RTS
     * column shows.
     *
     * `no_report` is the orders whose phone number has nothing behind it, which
     * is exactly where the rate comes back NULL. `has_report` is the rest, and
     * may carry a comparison against the number(s) typed beside the operator.
     *
     * Compared as a whole percent, because that is what the badge shows: a row
     * reading 30% should answer a "= 30" rather than nothing at all.
     */
    private function applyReport($query, string $report, Request $request)
    {
        $rate = CustomerRtsRisk::rateSql();

        if ($report === 'no_report') {
            return $query->whereRaw("{$rate} IS NULL");
        }

        $query->whereRaw("{$rate} IS NOT NULL");

        $operator = (string) $request->input('filter.rts_op');
        $value = $request->input('filter.rts_value');

        // Half a comparison narrows nothing — the report filter still stands.
        if (! is_numeric($value)) {
            return $query;
        }

        $percent = "ROUND({$rate} * 100)";

        if ($operator === 'between') {
            $upper = $request->input('filter.rts_value2');

            if (! is_numeric($upper)) {
                return $query;
            }

            // Ordered here rather than trusting the boxes: a range typed high
            // then low is still the range the user meant, and BETWEEN would
            // otherwise quietly match nothing.
            return $query->whereRaw("{$percent} BETWEEN ? AND ?", [
                min((float) $value, (float) $upper),
                max((float) $value, (float) $upper),
            ]);
        }

        $sql = self::RTS_OPERATORS[$operator] ?? null;

        if ($sql === null) {
            return $query;
        }

        return $query->whereRaw("{$percent} {$sql} ?", [(float) $value]);
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

    /**
     * The filter set the list runs under, shared with the tab counts below so a
     * filter added here cannot silently miss one of them.
     *
     * The counts pass `withStatus: false`: status is still declared — Spatie
     * rejects a filter it was not told about, and the request carries one
     * whenever a tab is open — but does nothing, so each tab shows its own
     * total instead of the count of the tab already open.
     *
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(Request $request, string $dateColumn, bool $withStatus = true): array
    {
        return [
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
            AllowedFilter::callback('status', $withStatus
                ? fn ($q, $v) => $q->whereIn(
                    'pancake_orders.status_name',
                    is_array($v) ? $v : explode(',', $v),
                )
                : fn () => null),
            AllowedFilter::callback('report', fn ($q, $v) => $this->applyReport($q, (string) $v, $request)),
            // Read by applyReport() off the request rather than filtering on
            // their own — declared so Spatie doesn't reject the request.
            AllowedFilter::callback('rts_op', fn () => null),
            AllowedFilter::callback('rts_value', fn () => null),
            AllowedFilter::callback('rts_value2', fn () => null),
        ];
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
            ->allowedFilters($this->allowedFilters($request, $dateColumn))
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

        // Per-status counts for the tab bar, run through the same filter set as
        // the rows so a tab cannot promise rows that aren't there once it is
        // clicked. Status alone is ignored, so each tab shows its own total.
        $statusCounts = QueryBuilder::for(clone $base)
            ->allowedFilters($this->allowedFilters($request, $dateColumn, withStatus: false))
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
            'rtsOperators' => self::RTS_COMPARISONS,
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
