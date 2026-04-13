<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\Ppw;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PpwController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $ppws = QueryBuilder::for(Ppw::where('inventory_ppws.workspace_id', $workspace->id))
            ->leftJoin('inventory_items', 'inventory_items.id', '=', 'inventory_ppws.inventory_item_id')
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_ppws.*')
            ->with(['inventoryItem.product'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('inventoryItem', function ($q) use ($value) {
                        $q->where('sku', 'like', "%{$value}%")
                          ->orWhereHas('product', function ($p) use ($value) {
                              $p->where('name', 'like', "%{$value}%");
                          });
                    });
                }),
                AllowedFilter::exact('inventory_item_id'),

                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('transaction_date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('transaction_date', '<=', $value);
                }),
            ])
            ->allowedSorts(['transaction_date', 'count', 'created_at', \Spatie\QueryBuilder\AllowedSort::field('item_sku', 'inventory_items.sku')])
            ->defaultSort('-transaction_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/inventory/ppw/index', [
            'ppws' => $ppws,
            'items' => InventoryItem::where('workspace_id', $workspace->id)->with('product')->get(),
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'transaction_date' => 'required|date',
            'count' => 'required|integer|min:0',
        ]);

        Ppw::create([
            'workspace_id' => $workspace->id,
            'inventory_item_id' => $request->inventory_item_id,
            'transaction_date' => $request->transaction_date,
            'count' => $request->count,
        ]);

        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug)
            ->with('success', 'PPW record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Ppw $ppw)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'transaction_date' => 'required|date',
            'count' => 'required|integer|min:0',
        ]);

        $ppw->update([
            'inventory_item_id' => $request->inventory_item_id,
            'transaction_date' => $request->transaction_date,
            'count' => $request->count,
        ]);

        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug)
            ->with('success', 'PPW record updated.');
    }

    public function destroy(Workspace $workspace, Ppw $ppw)
    {
        $ppw->delete();

        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug);
    }
}
