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
use Modules\Pancake\Filters\CustomerRtsReportFilter;
use Modules\Pancake\Filters\IgnoredFilter;
use Modules\Pancake\Filters\OrderDateFilter;
use Modules\Pancake\Filters\OrderNumberFilter;
use Modules\Pancake\Filters\OrderRiderFilter;
use Modules\Pancake\Filters\OrderSearchFilter;
use Modules\Pancake\Filters\OrderStatusFilter;
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
     * Every comparison the page may offer, in the order it lists them. The SQL
     * behind each one lives on CustomerRtsReportFilter::OPERATORS; `between`
     * takes a second number and is built by hand there.
     */
    public const RTS_COMPARISONS = ['gt', 'lt', 'eq', 'between'];

    /**
     * The filter set the list runs under, shared with the tab counts below so a
     * filter added here cannot silently miss one of them.
     *
     * Each entry is a Spatie filter class from Modules\Pancake\Filters, so the
     * rule itself is testable on its own and this method stays a list of what
     * the page may be narrowed by. The three that read another parameter —
     * `date_type` picks the column, `rts_*` carry the comparison — are declared
     * as IgnoredFilter so Spatie accepts them without applying them twice.
     *
     * The counts pass `withStatus: false`, which swaps the status rule for the
     * same no-op, so each tab shows its own total instead of the count of the
     * tab already open.
     *
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(Request $request, string $dateColumn, bool $withStatus = true): array
    {
        return [
            AllowedFilter::custom('search', new OrderSearchFilter),
            AllowedFilter::custom('order_number', new OrderNumberFilter),
            AllowedFilter::custom('date_from', new OrderDateFilter($dateColumn, '>=')),
            AllowedFilter::custom('date_to', new OrderDateFilter($dateColumn, '<=')),
            AllowedFilter::custom('date_type', new IgnoredFilter),
            AllowedFilter::custom('rider', new OrderRiderFilter),
            AllowedFilter::custom('status', $withStatus ? new OrderStatusFilter : new IgnoredFilter),
            AllowedFilter::custom('report', new CustomerRtsReportFilter(
                (string) $request->input('filter.rts_op'),
                $request->input('filter.rts_value'),
                $request->input('filter.rts_value2'),
            )),
            AllowedFilter::custom('rts_op', new IgnoredFilter),
            AllowedFilter::custom('rts_value', new IgnoredFilter),
            AllowedFilter::custom('rts_value2', new IgnoredFilter),
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
