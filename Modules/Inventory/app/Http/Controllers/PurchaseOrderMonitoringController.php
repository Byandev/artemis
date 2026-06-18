<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PurchaseOrderMonitoringController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);

        $items = QueryBuilder::for($this->scopedItems($workspace))
            ->allowedFilters([
                AllowedFilter::callback('search', fn (Builder $query, $value) => $this->applySearch($query, $value)),
                AllowedFilter::callback('start_date', fn (Builder $query, $value) => $query->whereHas('purchasedOrder', fn (Builder $po) => $po->whereDate('issue_date', '>=', $value))),
                AllowedFilter::callback('end_date', fn (Builder $query, $value) => $query->whereHas('purchasedOrder', fn (Builder $po) => $po->whereDate('issue_date', '<=', $value))),
                AllowedFilter::callback('status', fn (Builder $query, $value) => match ($value) {
                    'waiting' => $query->waiting(),
                    'partial' => $query->partiallyDelivered(),
                    'delivered' => $query->fullyDelivered(),
                    default => $query,
                }),
                AllowedFilter::callback('delivery_status', fn (Builder $query, $value) => match ($value) {
                    'delayed' => $query->delayed(),
                    'ontime' => $query->onSchedule(),
                    default => $query,
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('po_issue_date'),
                AllowedSort::field('expected_delivery_date'),
                AllowedSort::field('count'),
                'delivered_qty',
            ])
            ->defaultSort('-po_issue_date')
            ->addSelect(['po_issue_date' => PurchasedOrder::select('issue_date')
                ->whereColumn('id', 'inventory_purchased_order_items.inventory_purchased_order_id')])
            ->withSum('deliveries as delivered_qty', 'qty')
            ->with([
                'purchasedOrder:id,issue_date,cust_po_no,control_no,delivery_no,status',
                'inventoryItem:id,sku,product_id',
                'inventoryItem.product:id,name',
                'deliveries' => fn ($query) => $query->orderBy('delivery_date')->orderBy('id'),
            ])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/inventory/po-monitoring/index', [
            'workspace' => $workspace,
            'items' => $items,
            'summary' => $this->summary($workspace, $request),
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function storeDelivery(Request $request, Workspace $workspace, PurchasedOrderItem $purchasedOrderItem)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($purchasedOrderItem, $workspace);

        $purchasedOrderItem->deliveries()->create($this->validateDelivery($request));

        return back()->with('success', 'Delivery recorded.');
    }

    public function updateDelivery(Request $request, Workspace $workspace, PurchasedOrderItemDelivery $delivery)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($delivery->item, $workspace);

        $delivery->update($this->validateDelivery($request));

        return back()->with('success', 'Delivery updated.');
    }

    public function destroyDelivery(Workspace $workspace, PurchasedOrderItemDelivery $delivery)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($delivery->item, $workspace);

        $delivery->delete();

        return back()->with('success', 'Delivery deleted.');
    }

    public function updateExpectedDelivery(Request $request, Workspace $workspace, PurchasedOrderItem $purchasedOrderItem)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($purchasedOrderItem, $workspace);

        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date_format:Y-m-d|date',
        ]);

        $purchasedOrderItem->update([
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
        ]);

        return back()->with('success', 'Expected delivery date updated.');
    }

    /** Purchase order items belonging to the given workspace. */
    private function scopedItems(Workspace $workspace): Builder
    {
        return PurchasedOrderItem::query()
            ->whereHas('purchasedOrder', fn (Builder $po) => $po->where('workspace_id', $workspace->id));
    }

    /** Status breakdown for the search/date-filtered set (independent of the status chips). */
    private function summary(Workspace $workspace, Request $request): array
    {
        $totalQuery = $this->summaryBase($workspace, $request);
        $waitingQuery = $this->summaryBase($workspace, $request)->waiting();
        $partialQuery = $this->summaryBase($workspace, $request)->partiallyDelivered();
        $deliveredQuery = $this->summaryBase($workspace, $request)->fullyDelivered();
        $delayedQuery = $this->summaryBase($workspace, $request)->delayed();

        return [
            'total' => $totalQuery->count(),
            'waiting' => $waitingQuery->count(),
            'partial' => $partialQuery->count(),
            'delivered' => $deliveredQuery->count(),
            'delayed' => $delayedQuery->count(),
        ];
    }

    /** A fresh workspace + search/date filtered item query for a single summary metric. */
    private function summaryBase(Workspace $workspace, Request $request): Builder
    {
        $query = $this->scopedItems($workspace);

        if ($search = $request->input('filter.search')) {
            $this->applySearch($query, $search);
        }
        if ($start = $request->input('filter.start_date')) {
            $query->whereHas('purchasedOrder', fn (Builder $po) => $po->whereDate('issue_date', '>=', $start));
        }
        if ($end = $request->input('filter.end_date')) {
            $query->whereHas('purchasedOrder', fn (Builder $po) => $po->whereDate('issue_date', '<=', $end));
        }

        return $query;
    }

    private function applySearch(Builder $query, string $value): Builder
    {
        return $query->where(function (Builder $q) use ($value) {
            $q->whereHas('purchasedOrder', fn (Builder $po) => $po
                ->where('cust_po_no', 'like', "%{$value}%")
                ->orWhere('control_no', 'like', "%{$value}%")
                ->orWhere('delivery_no', 'like', "%{$value}%"))
                ->orWhereHas('inventoryItem', fn (Builder $item) => $item->where('sku', 'like', "%{$value}%"))
                ->orWhereHas('inventoryItem.product', fn (Builder $product) => $product->where('name', 'like', "%{$value}%"))
                ->orWhereHas('deliveries', fn (Builder $delivery) => $delivery->where('delivery_no', 'like', "%{$value}%"));
        });
    }

    private function validateDelivery(Request $request): array
    {
        return $request->validate([
            'delivery_date' => 'required|date_format:Y-m-d|date',
            'delivery_no' => 'nullable|string|max:255',
            'qty' => 'required|integer|min:1',
        ]);
    }

    private function ensureItemBelongsToWorkspace(?PurchasedOrderItem $item, Workspace $workspace): void
    {
        abort_unless($item?->purchasedOrder?->workspace_id === $workspace->id, 404);
    }
}
