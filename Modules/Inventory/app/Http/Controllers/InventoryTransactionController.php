<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryTransactionController extends Controller
{
    use AuthorizesRequests;

    private function buildQuery(Workspace $workspace): QueryBuilder
    {
        return QueryBuilder::for(InventoryTransaction::where('inventory_transactions.workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('ref_no', 'like', "%{$value}%")
                            ->orWhereHas('inventoryItem', function ($itemQuery) use ($value) {
                                $itemQuery->where('sku', 'like', "%{$value}%")
                                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$value}%"));
                            });
                    });
                }),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('date', '<=', $value);
                }),
            ])
            ->allowedSorts([
                'date',
                'ref_no',
                'po_qty_in',
                'po_qty_out',
                'rts_goods_in',
                'rts_goods_out',
                'rts_bad',
                'lost',
                'remaining_qty',
                'inventory_remaining_stock',
                'created_at',
                AllowedSort::callback('inventory_item', function ($query, $descending) {
                    $query->join('inventory_items', 'inventory_transactions.inventory_item_id', '=', 'inventory_items.id')
                        ->orderBy('inventory_items.sku', $descending ? 'desc' : 'asc')
                        ->select('inventory_transactions.*');
                }),
            ])
            // Group rows by inventory item in the backend (one item's transactions sit
            // together), most recent first within each item.
            ->defaultSort(['inventory_item_id', '-id']);
    }

    /**
     * Aggregated query: one row per inventory item per date, summing the flow columns
     * across that item's transactions on each day (respecting the search/date filters).
     */
    private function buildSummaryQuery(Workspace $workspace): QueryBuilder
    {
        $aggregated = InventoryTransaction::query()
            ->where('inventory_transactions.workspace_id', $workspace->id)
            ->select('inventory_item_id', 'date')
            ->selectRaw('SUM(po_qty_in) as po_qty_in')
            ->selectRaw('SUM(po_qty_out) as po_qty_out')
            ->selectRaw('SUM(rts_goods_in) as rts_goods_in')
            ->selectRaw('SUM(rts_goods_out) as rts_goods_out')
            ->selectRaw('SUM(rts_bad) as rts_bad')
            ->selectRaw('SUM(lost) as lost')
            ->selectRaw('COUNT(*) as transaction_count')
            // The latest ERP stock recorded on that day for the item.
            ->selectRaw('MAX(inventory_remaining_stock) as inventory_remaining_stock')
            ->whereNotNull('inventory_item_id')
            ->groupBy('inventory_item_id', 'date');

        return QueryBuilder::for($aggregated)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('inventoryItem', function ($itemQuery) use ($value) {
                        $itemQuery->where('sku', 'like', "%{$value}%")
                            ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$value}%"));
                    });
                }),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('date', '<=', $value);
                }),
            ])
            ->allowedSorts([
                'date',
                'po_qty_in',
                'po_qty_out',
                'rts_goods_in',
                'rts_goods_out',
                'rts_bad',
                'lost',
            ])
            ->defaultSort(['inventory_item_id', '-date']);
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Transaction Logs', $workspace);

        $summarize = $request->boolean('summarize');

        $query = $summarize
            ? $this->buildSummaryQuery($workspace)
            : $this->buildQuery($workspace);

        $inventory = $query
            ->with(['inventoryItem.product'])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/inventory/inventory_transaction/index', [
            'workspace' => $workspace,
            'inventory' => $inventory,
            'items' => InventoryItem::where('workspace_id', $workspace->id)->with('product')->get(),
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'summarize' => $summarize,
            ],
            'users' => $workspace->users()->get(['users.id', 'users.name']),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('Create Transaction Logs', $workspace);

        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'date' => [
                'required',
                'date',
                'before_or_equal:9999-12-31',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
            ],
            'ref_no' => 'required|string|max:255|unique:inventory_transactions,ref_no,NULL,id,workspace_id,'.$workspace->id,
            'po_qty_in' => 'required|integer|min:0',
            'po_qty_out' => 'required|integer|min:0',
            'rts_goods_in' => 'required|integer|min:0',
            'rts_goods_out' => 'required|integer|min:0',
            'rts_bad' => 'required|integer|min:0',
            'lost' => 'required|integer|min:0',
            'remaining_qty' => 'required|numeric',
        ]);

        $workspace->inventoryTransactions()->create($validated);

        return redirect()->back()->with('success', 'Entry created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryTransaction $transaction)
    {
        $this->authorize('Edit Transaction Logs', $workspace);

        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'date' => 'required|date',
            'ref_no' => 'required|string|max:255|unique:inventory_transactions,ref_no,'.$transaction->id.',id,workspace_id,'.$workspace->id,
            'po_qty_in' => 'required|integer|min:0',
            'po_qty_out' => 'required|integer|min:0',
            'rts_goods_in' => 'required|integer|min:0',
            'rts_goods_out' => 'required|integer|min:0',
            'rts_bad' => 'required|integer|min:0',
            'lost' => 'required|integer|min:0',
            'remaining_qty' => 'required|numeric',
        ]);

        $transaction->update($validated);

        return redirect()->back()->with('success', 'Entry updated successfully.');
    }

    /**
     * Inline edit of the remaining quantity — this is a physical audit. The edited row
     * becomes the anchor its offset rides forward from, and every later row is re-leveled.
     */
    public function updateRemainingQty(Request $request, Workspace $workspace, InventoryTransaction $transaction)
    {
        $this->authorize('Edit Transaction Logs', $workspace);

        $validated = $request->validate([
            'remaining_qty' => 'required|numeric',
        ]);

        $transaction->update([
            'remaining_qty' => $validated['remaining_qty'],
            'is_audited' => true,
        ]);

        $transaction->inventoryItem?->recalculateActualStock();

        return redirect()->back()->with('success', 'Remaining quantity updated.');
    }

    public function destroy(Workspace $workspace, InventoryTransaction $transaction)
    {
        $this->authorize('Delete Transaction Logs', $workspace);

        $transaction->delete();

        return redirect()->back()->with('success', 'Entry permanently deleted.');
    }
}
