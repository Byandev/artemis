<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $items = QueryBuilder::for(InventoryItem::where('inventory_items.workspace_id', $workspace->id))
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('sku', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('product_id'),

                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('inventory_items.product_id', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('inventory_items.product_id', '<=', $value);
                }),
            ])
            ->allowedSorts([
                'id',
                'product_id',
                'sku',
                'sales_keywords',
                'transaction_keywords',
                \Spatie\QueryBuilder\AllowedSort::field('product_name', 'products.name')
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/inventory/items/index', [
            'items' => $items,
            'products' => Product::where('workspace_id', $workspace->id)->get(),
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
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:255',
            'sales_keywords' => 'nullable|string',
            'transaction_keywords' => 'nullable|string',
        ]);

        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'product_id' => $request->product_id,
            'sku' => $request->sku,
            'sales_keywords' => $request->sales_keywords,
            'transaction_keywords' => $request->transaction_keywords,
        ]);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:255',
            'sales_keywords' => 'nullable|string',
            'transaction_keywords' => 'nullable|string',
        ]);

        $item->update([
            'product_id' => $request->product_id,
            'sku' => $request->sku,
            'sales_keywords' => $request->sales_keywords,
            'transaction_keywords' => $request->transaction_keywords,
        ]);
        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Inventory Items record updated.');
    }

    public function destroy(Workspace $workspace, InventoryItem $item)
    {
        $item->delete();
        return redirect()->route('workspaces.inventory.item.index', $workspace->slug);
    }
}
