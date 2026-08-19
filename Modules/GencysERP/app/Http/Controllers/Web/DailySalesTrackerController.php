<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\Inventory\Models\InventoryUnitCode;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DailySalesTrackerController extends Controller
{
    use AuthorizesRequests;

    /** Columns the table may be sorted by. */
    private const SORTABLE = [
        'order_date',
        'csr',
        'verifier_name',
        'upsell_by',
        'customer_name',
        'address',
        'province',
        'city',
        'brgy',
        'contact',
        'order_details',
        'total_qty',
        'price_final',
        'price_initial',
        'shipping_fee',
        'page',
        'platform',
        'tracking_number',
        'courier',
        'parcel_status',
        'order_status',
        'mop',
        'encoded_date',
        'parcel_updated_date',
        'shipped_out_date',
        'date_added',
        'price_upsell',
        'intern_brands_name',
        'total_cog',
    ];

    /**
     * Scalar columns the test-order form may set. Everything is nullable in the
     * schema, so test rows can be as sparse or as complete as the scenario needs.
     *
     * `order_details` is deliberately absent — it's derived from the submitted
     * unit-code items, not typed by hand.
     */
    private const TEST_ORDER_FIELDS = [
        'order_no' => ['nullable', 'string', 'max:255'],
        'order_date' => ['nullable', 'date'],
        'csr' => ['nullable', 'string', 'max:255'],
        'verifier_name' => ['nullable', 'string', 'max:255'],
        'upsell_by' => ['nullable', 'string', 'max:255'],
        'customer_name' => ['nullable', 'string', 'max:255'],
        'address' => ['nullable', 'string', 'max:1000'],
        'province' => ['nullable', 'string', 'max:255'],
        'city' => ['nullable', 'string', 'max:255'],
        'brgy' => ['nullable', 'string', 'max:255'],
        'contact' => ['nullable', 'string', 'max:255'],
        'total_qty' => ['nullable', 'integer', 'min:0'],
        'price_final' => ['nullable', 'numeric'],
        'price_initial' => ['nullable', 'numeric'],
        'shipping_fee' => ['nullable', 'numeric'],
        'page' => ['nullable', 'string', 'max:255'],
        'platform' => ['nullable', 'string', 'max:255'],
        'tracking_number' => ['nullable', 'string', 'max:255'],
        'courier' => ['nullable', 'string', 'max:255'],
        'parcel_status' => ['nullable', 'string', 'max:255'],
        'order_status' => ['nullable', 'string', 'max:255'],
        'mop' => ['nullable', 'string', 'max:255'],
        'encoded_date' => ['nullable', 'date'],
        'parcel_updated_date' => ['nullable', 'date'],
        'shipped_out_date' => ['nullable', 'date'],
        'date_added' => ['nullable', 'date'],
        'price_upsell' => ['nullable', 'numeric'],
        'intern_brands_name' => ['nullable', 'string', 'max:255'],
        'total_cog' => ['nullable', 'numeric'],
    ];

    /**
     * Date columns exposed as range filters. Each becomes a pair of
     * `{column}_start` / `{column}_end` filters (inclusive whereDate bounds).
     */
    private const DATE_RANGES = [
        'order_date',
        'shipped_out_date',
        'encoded_date',
        'parcel_updated_date',
        'date_added',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('View Daily Sales Tracker', $workspace);

        $orders = QueryBuilder::for(
            GencysDailySalesOrder::query()->where('workspace_id', $workspace->id)
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (['csr', 'customer_name', 'contact', 'tracking_number', 'page', 'order_details', 'intern_brands_name', 'order_status', 'parcel_status'] as $column) {
                            $q->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }),
                ...$this->dateRangeFilters(),
                AllowedFilter::exact('csr'),
                AllowedFilter::exact('platform'),
                AllowedFilter::exact('parcel_status'),
                AllowedFilter::exact('order_status'),
            ])
            ->allowedSorts(self::SORTABLE)
            ->defaultSort('-order_date')
            ->with('items:id,order_id,quantity,sku')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $distinct = fn (string $column) => GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return Inertia::render('workspaces/gencys/daily-sales-tracker/index', [
            'workspace' => $workspace,
            'orders' => $orders,
            'csrs' => $distinct('csr'),
            'platforms' => $distinct('platform'),
            'parcelStatuses' => $distinct('parcel_status'),
            'orderStatuses' => $distinct('order_status'),
            'canManageTestOrders' => ! app()->isProduction(),
            // Only the test-order form needs these, so skip the query in production.
            'unitCodes' => app()->isProduction() ? [] : $this->unitCodeOptions($workspace),
            'query' => [
                'sort' => $request->input('sort', '-order_date'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Create a hand-made order for testing downstream features (income
     * statements, the daily sales table) without waiting on an ERP sync.
     *
     * TESTING ONLY — blocked in production, and the routes aren't even
     * registered there. Rows land in the reserved test id range so a real sync
     * can never overwrite them and they stay identifiable.
     *
     * Line items are picked from the workspace's unit codes, which is how real
     * Gencys orders work: each `gencys_order_items.sku` is a unit-code label,
     * and the inventory snapshot (GencysDemandSync) expands it into its
     * component inventory items. `order_details` is rebuilt from those picks in
     * Gencys' own "{qty}x{unit code}" format so the table and the product
     * groupings that read it behave the same as for synced rows.
     */
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->abortIfProduction();
        $this->authorize('View Daily Sales Tracker', $workspace);

        $data = $request->validate([
            ...self::TEST_ORDER_FIELDS,
            'items' => ['required', 'array', 'min:1'],
            // Must be a real unit code in this workspace, otherwise the row
            // would be dead weight — nothing downstream could expand it.
            'items.*.unit_code' => [
                'required',
                'string',
                Rule::exists('inventory_unit_codes', 'unit_code')
                    ->where('workspace_id', $workspace->id),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $items = collect($data['items'])->map(fn (array $item) => [
            'sku' => trim($item['unit_code']),
            'quantity' => (int) $item['quantity'],
        ]);

        $attributes = Arr::except($data, ['items']);
        $attributes['order_details'] = $items
            ->map(fn (array $item) => "{$item['quantity']}x{$item['sku']}")
            ->implode(',');
        // Left blank on the form, total_qty is just the sum of the picked lines.
        $attributes['total_qty'] ??= $items->sum('quantity');

        $order = DB::transaction(function () use ($workspace, $attributes, $items) {
            $order = GencysDailySalesOrder::create([
                'id' => GencysDailySalesOrder::nextTestId(),
                'workspace_id' => $workspace->id,
                ...$attributes,
            ]);

            $order->items()->createMany($items->all());

            return $order;
        });

        return back()->with('success', "Test order #{$order->id} created.");
    }

    /**
     * Delete a test order. TESTING ONLY — refuses to touch synced Gencys rows.
     */
    public function destroy(Workspace $workspace, GencysDailySalesOrder $order): RedirectResponse
    {
        $this->abortIfProduction();
        $this->authorize('View Daily Sales Tracker', $workspace);

        abort_unless($order->workspace_id === $workspace->id, 404);
        abort_unless($order->is_test, 403, 'Only test orders can be deleted.');

        $order->items()->delete();
        $order->delete();

        return back()->with('success', 'Test order deleted.');
    }

    /** The test-order endpoints must never be reachable in production. */
    private function abortIfProduction(): void
    {
        abort_if(app()->isProduction(), 404);
    }

    /**
     * Unit codes the test-order form can pick line items from. Deliberately not
     * team-scoped (unlike the unit-codes page): a tester needs every code, not
     * just the ones their team owns.
     *
     * @return array<int, array{unit_code: string, sku: ?string, total_amount: ?string}>
     */
    private function unitCodeOptions(Workspace $workspace): array
    {
        return InventoryUnitCode::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('unit_code')
            ->where('unit_code', '!=', '')
            ->orderBy('unit_code')
            ->get(['unit_code', 'sku', 'total_amount'])
            ->map(fn (InventoryUnitCode $code) => [
                'unit_code' => (string) $code->unit_code,
                'sku' => $code->sku,
                'total_amount' => $code->total_amount,
            ])
            ->all();
    }

    /**
     * Build inclusive `{column}_start` / `{column}_end` range filters for every
     * date column in self::DATE_RANGES.
     *
     * @return array<int, AllowedFilter>
     */
    private function dateRangeFilters(): array
    {
        $filters = [];

        foreach (self::DATE_RANGES as $column) {
            $filters[] = AllowedFilter::callback(
                "{$column}_start",
                fn (Builder $query, $value) => $query->whereDate($column, '>=', $value),
            );
            $filters[] = AllowedFilter::callback(
                "{$column}_end",
                fn (Builder $query, $value) => $query->whereDate($column, '<=', $value),
            );
        }

        return $filters;
    }
}
