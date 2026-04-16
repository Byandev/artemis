<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedSort;

class InventoryTransactionController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

       $inventory = QueryBuilder::for(InventoryTransaction::where('inventory_transactions.workspace_id', $workspace->id))
            ->with(['inventoryItem.product'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('ref_no', 'like', "%{$value}%");
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
                'created_at',
                AllowedSort::callback('inventory_item', function ($query, $descending) {
                    $query->join('inventory_items', 'inventory_transactions.inventory_item_id', '=', 'inventory_items.id')
                        ->orderBy('inventory_items.sku', $descending ? 'desc' : 'asc')
                        ->select('inventory_transactions.*'); 
                }),
            ])
            ->defaultSort('-date')
            ->paginate(10)
            ->withQueryString();

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/inventory/inventory_transaction/index', [
            'workspace' => $workspace,
            'inventory' => $inventory,
            'items' => InventoryItem::where('workspace_id', $workspace->id)->with('product')->get(),
            'query' => [
                ...$request->only(['sort', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'date' => [
                'required',
                'date',
                'before_or_equal:9999-12-31',
                'regex:/^\d{4}-\d{2}-\d{2}$/', 
            ],
            'ref_no' => 'required|string|max:255|unique:inventory_transactions,ref_no,NULL,id,workspace_id,' . $workspace->id,
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
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'date' => 'required|date',
            'ref_no' => 'required|string|max:255|unique:inventory_transactions,ref_no,' . $transaction->id . ',id,workspace_id,' . $workspace->id,
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

    public function destroy(Workspace $workspace, InventoryTransaction $transaction)
    {
        $transaction->delete();

        return redirect()->back()->with('success', 'Entry permanently deleted.');
    }
}
