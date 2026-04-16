<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
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
            // unfulfilled_count is a stored column updated manually
            // waiting for delivery = purchased order items where order status = 6 (Waiting For Delivery)
            ->withSum('waitingForDeliveryItems as waiting_for_delivery_stocks', 'count')
            // current stocks = latest remaining_qty from transactions
            ->addSelect([
                'current_stocks' => InventoryTransaction::select('remaining_qty')
                    ->whereColumn('inventory_item_id', 'inventory_items.id')
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            // three_days_average is a stored column updated hourly by inventory:update-averages
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('sku', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('product_id'),
            ])
            ->allowedSorts([
                'id',
                'product_id',
                'sku',
                'lead_time',
                'unfulfilled_count',
                'three_days_average',
                'created_at',
                \Spatie\QueryBuilder\AllowedSort::field('product_name', 'products.name'),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Compute derived metrics on each item
        $items->through(function (InventoryItem $item) {
            $current  = (float) ($item->current_stocks ?? 0);
            $waiting  = (float) ($item->waiting_for_delivery_stocks ?? 0);
            $unfulfilled = (float) ($item->unfulfilled_count ?? 0);
            $item->unfulfilled = $unfulfilled;
            $avg      = (float) ($item->three_days_average ?? 0);
            $leadTime = (int)   ($item->lead_time ?? 0);

            $remaining = $current + $waiting - $unfulfilled;

            $item->remaining_after_fulfillment = round($remaining, 2);
            $item->days_it_can_last = $avg > 0 ? round($remaining / $avg, 1) : null;
            $item->po_needed = round(max(0, ($leadTime * $avg) - $waiting), 2);

            return $item;
        });

        return Inertia::render('workspaces/inventory/items/index', [
            'items' => $items,
            'products' => Product::where('workspace_id', $workspace->id)->get(),
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'product_id'            => 'required|exists:products,id',
            'sku'                   => 'required|string|max:255|unique:inventory_items,sku,NULL,id,workspace_id,'.$workspace->id,
            'sales_keywords'        => 'nullable|string',
            'transaction_keywords'  => 'nullable|string',
            'lead_time'             => 'nullable|integer|min:0',
            'unfulfilled_count'     => 'nullable|integer|min:0',
            'three_days_average'    => 'nullable|numeric|min:0',
        ]);

        InventoryItem::create([
            'workspace_id'          => $workspace->id,
            'product_id'            => $request->product_id,
            'sku'                   => $request->sku,
            'sales_keywords'        => $request->sales_keywords,
            'transaction_keywords'  => $request->transaction_keywords,
            'lead_time'             => $request->lead_time ?? 0,
            'unfulfilled_count'     => $request->unfulfilled_count ?? 0,
            'three_days_average'    => $request->three_days_average ?? 0,
        ]);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $request->validate([
            'product_id'            => 'required|exists:products,id',
            'sku'                   => 'required|string|max:255',
            'sales_keywords'        => 'nullable|string',
            'transaction_keywords'  => 'nullable|string',
            'lead_time'             => 'nullable|integer|min:0',
            'unfulfilled_count'     => 'nullable|integer|min:0',
            'three_days_average'    => 'nullable|numeric|min:0',
        ]);

        $item->update([
            'product_id'            => $request->product_id,
            'sku'                   => $request->sku,
            'sales_keywords'        => $request->sales_keywords,
            'transaction_keywords'  => $request->transaction_keywords,
            'lead_time'             => $request->lead_time ?? 0,
            'unfulfilled_count'     => $request->unfulfilled_count ?? 0,
            'three_days_average'    => $request->three_days_average ?? 0,
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
