<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysDailySalesOrder;

class DailySalesTrackerController extends Controller
{
    use AuthorizesRequests;

    /** Columns the table may be sorted by (frontend field => DB column). */
    private const SORTABLE = [
        'order_date' => 'order_date',
        'csr' => 'csr',
        'verifier_name' => 'verifier_name',
        'upsell_by' => 'upsell_by',
        'contact' => 'contact',
        'order_details' => 'order_details',
        'total_qty' => 'total_qty',
        'page' => 'page',
        'platform' => 'platform',
        'tracking_number' => 'tracking_number',
        'parcel_status' => 'parcel_status',
        'order_status' => 'order_status',
        'encoded_date' => 'encoded_date',
        'parcel_updated_date' => 'parcel_updated_date',
        'shipped_out_date' => 'shipped_out_date',
        'date_added' => 'date_added',
        'price_upsell' => 'price_upsell',
        'intern_brands_name' => 'intern_brands_name',
        'total_cog' => 'total_cog',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('View Daily Sales Tracker', $workspace);

        [$sortColumn, $sortDir, $sortParam] = $this->resolveSort($request);

        $orders = $this->filtered($request, $workspace)
            ->with('items:id,order_id,quantity,sku')
            ->orderBy($sortColumn, $sortDir)
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
            'query' => [
                'sort' => $sortParam,
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Workspace-scoped query with the request's search / date / csr filters applied. */
    private function filtered(Request $request, Workspace $workspace): Builder
    {
        $query = GencysDailySalesOrder::query()->where('workspace_id', $workspace->id);

        if ($search = $request->input('filter.search')) {
            $query->where(function (Builder $q) use ($search) {
                foreach (['csr', 'contact', 'tracking_number', 'page', 'order_details', 'intern_brands_name', 'order_status', 'parcel_status'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($start = $request->input('filter.start_date')) {
            $query->whereDate('order_date', '>=', $start);
        }

        if ($end = $request->input('filter.end_date')) {
            $query->whereDate('order_date', '<=', $end);
        }

        // Multi-select filters: accept an array (whereIn) or a single value.
        foreach (['csr', 'platform', 'parcel_status', 'order_status'] as $column) {
            $values = array_values(array_filter(
                (array) $request->input("filter.{$column}", []),
                fn ($v) => $v !== null && $v !== '',
            ));

            if (! empty($values)) {
                $query->whereIn($column, $values);
            }
        }

        return $query;
    }

    /** Resolve the sort param ("-order_date") into [column, direction, normalized param]. */
    private function resolveSort(Request $request): array
    {
        $raw = (string) $request->input('sort', '-order_date');
        $desc = str_starts_with($raw, '-');
        $field = $desc ? substr($raw, 1) : $raw;

        $column = self::SORTABLE[$field] ?? 'order_date';
        $field = array_search($column, self::SORTABLE, true) ?: 'order_date';

        return [$column, $desc ? 'desc' : 'asc', ($desc ? '-' : '').$field];
    }
}
