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
        'total_qty' => 'total_qty',
        'total_cog' => 'total_cog',
        'shipped_out_date' => 'shipped_out_date',
        'page' => 'page',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('View Daily Sales Tracker', $workspace);

        $query = $this->filtered($request, $workspace);

        $summary = [
            'total_orders' => (clone $query)->count(),
            'total_qty' => (int) (clone $query)->sum('total_qty'),
            'total_cog' => (float) (clone $query)->sum('total_cog'),
            'total_upsell' => (float) (clone $query)->sum('price_upsell'),
        ];

        [$sortColumn, $sortDir, $sortParam] = $this->resolveSort($request);

        $orders = $query
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $csrs = GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereNotNull('csr')
            ->distinct()
            ->orderBy('csr')
            ->pluck('csr');

        return Inertia::render('workspaces/gencys/daily-sales-tracker/index', [
            'workspace' => $workspace,
            'orders' => $orders,
            'summary' => $summary,
            'csrs' => $csrs,
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

        if ($csr = $request->input('filter.csr')) {
            $query->where('csr', $csr);
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
