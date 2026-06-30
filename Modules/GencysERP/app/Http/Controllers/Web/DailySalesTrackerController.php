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
                AllowedFilter::callback('start_date', fn (Builder $query, $value) => $query->whereDate('order_date', '>=', $value)),
                AllowedFilter::callback('end_date', fn (Builder $query, $value) => $query->whereDate('order_date', '<=', $value)),
                AllowedFilter::callback('shipped_out_start_date', fn (Builder $query, $value) => $query->whereDate('shipped_out_date', '>=', $value)),
                AllowedFilter::callback('shipped_out_end_date', fn (Builder $query, $value) => $query->whereDate('shipped_out_date', '<=', $value)),
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
            'query' => [
                'sort' => $request->input('sort', '-order_date'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
