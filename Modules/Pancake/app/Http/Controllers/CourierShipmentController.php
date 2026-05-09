<?php

namespace Modules\Pancake\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\CourierShipment;
use Modules\Pancake\Services\CourierShipmentImporter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CourierShipmentController extends Controller
{
    use AuthorizesRequests;

    private function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCourierShipments->value, $workspace);

        $base = CourierShipment::where('workspace_id', $workspace->id);

        $shipments = QueryBuilder::for(clone $base)
            ->with([
                'pancakeOrder:id,order_number,status,status_name,parcel_status,delivered_at,returned_at',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('waybill_no', 'like', "%{$v}%")
                        ->orWhere('order_number', 'like', "%{$v}%")
                        ->orWhere('receiver', 'like', "%{$v}%")
                        ->orWhere('receiver_cellphone', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('courier'),
                AllowedFilter::exact('order_status'),
                AllowedFilter::callback('matched', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('pancake_order_id')
                    : $q->whereNull('pancake_order_id')),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('preferred_pickup_date', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('preferred_pickup_date', '<=', $v)),
            ])
            ->allowedSorts(['preferred_pickup_date', 'total_shipping_cost', 'cod_fee', 'cod', 'waybill_no', 'created_at'])
            ->defaultSort('-preferred_pickup_date')
            ->paginate((int) $request->input('per_page', 50))
            ->withQueryString();

        $totalsQuery = QueryBuilder::for(clone $base)
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('waybill_no', 'like', "%{$v}%")
                        ->orWhere('order_number', 'like', "%{$v}%")
                        ->orWhere('receiver', 'like', "%{$v}%")
                        ->orWhere('receiver_cellphone', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('courier'),
                AllowedFilter::exact('order_status'),
                AllowedFilter::callback('matched', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('pancake_order_id')
                    : $q->whereNull('pancake_order_id')),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('preferred_pickup_date', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('preferred_pickup_date', '<=', $v)),
            ])
            ->getEloquentBuilder();

        $totals = (clone $totalsQuery)
            ->selectRaw('
                COUNT(*) as count,
                SUM(total_shipping_cost) as total_shipping_cost,
                SUM(cod_fee) as cod_fee,
                SUM(receivable_freight) as receivable_freight,
                SUM(cod) as cod,
                SUM(CASE WHEN pancake_order_id IS NOT NULL THEN 1 ELSE 0 END) as matched_count,
                SUM(CASE WHEN pancake_order_id IS NOT NULL THEN total_shipping_cost ELSE 0 END) as matched_total_shipping_cost,
                SUM(CASE WHEN pancake_order_id IS NOT NULL THEN cod_fee ELSE 0 END) as matched_cod_fee
            ')
            ->first();

        return Inertia::render('workspaces/pancake/courier-shipments/index', [
            'workspace' => $workspace,
            'shipments' => $shipments,
            'totals' => [
                'count' => (int) ($totals->count ?? 0),
                'matched_count' => (int) ($totals->matched_count ?? 0),
                'total_shipping_cost' => (float) ($totals->total_shipping_cost ?? 0),
                'cod_fee' => (float) ($totals->cod_fee ?? 0),
                'receivable_freight' => (float) ($totals->receivable_freight ?? 0),
                'cod' => (float) ($totals->cod ?? 0),
                'matched_total_shipping_cost' => (float) ($totals->matched_total_shipping_cost ?? 0),
                'matched_cod_fee' => (float) ($totals->matched_cod_fee ?? 0),
            ],
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function import(Request $request, Workspace $workspace, CourierShipmentImporter $importer)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ImportCourierShipments->value, $workspace);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'courier' => ['required', 'in:jt'],
        ]);

        $result = $importer->importJt($workspace, $request->file('file'));

        return redirect()->back()->with('success', sprintf(
            'Imported %d rows (%d upserted). Matched %d / %d to pancake orders.',
            $result['rows_read'],
            $result['upserted'],
            $result['matched'],
            $result['rows_read'],
        ));
    }
}
