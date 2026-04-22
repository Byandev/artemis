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
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {

        $currentStocksSql = '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';

        $waitingStocksSql = '(SELECT SUM(count) FROM purchased_order_items 
                              WHERE inventory_item_id = inventory_items.id 
                              AND EXISTS (SELECT 1 FROM purchased_orders 
                                          WHERE purchased_orders.id = purchased_order_items.purchased_order_id 
                                          AND status = 6))';

        $items = QueryBuilder::for(InventoryItem::where('inventory_items.workspace_id', $workspace->id))
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            // 2. Add subqueried/summed values for display
            ->withSum('waitingForDeliveryItems as waiting_for_delivery_stocks', 'count')
            ->addSelect([
                'current_stocks' => InventoryTransaction::select('remaining_qty')
                    ->whereColumn('inventory_item_id', 'inventory_items.id')
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->limit(1),
            ])

            ->selectRaw("
                (COALESCE($currentStocksSql, 0) + COALESCE($waitingStocksSql, 0) - inventory_items.unfulfilled_count) as remaining_after_fulfillment,
                
                CASE 
                    WHEN three_days_average > 0 
                    THEN (COALESCE($currentStocksSql, 0) + COALESCE($waitingStocksSql, 0) - inventory_items.unfulfilled_count) / three_days_average 
                    ELSE NULL 
                END as days_it_can_last,

                GREATEST(0, (lead_time * three_days_average) - COALESCE($waitingStocksSql, 0)) as po_needed
            ")
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
                AllowedSort::field('product_name', 'products.name'),
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->withQueryString();

        // 4. Formatting loop for frontend display
        $items->through(function (InventoryItem $item) {
            $current = (float) ($item->current_stocks ?? 0);
            $waiting = (float) ($item->waiting_for_delivery_stocks ?? 0);
            $unfulfilled = (float) ($item->unfulfilled_count ?? 0);
            $item->unfulfilled = $unfulfilled;
            $avg = (float) ($item->three_days_average ?? 0);
            $leadTime = (int) ($item->lead_time ?? 0);

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
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:255|unique:inventory_items,sku,NULL,id,workspace_id,'.$workspace->id,
            'sales_keywords' => 'nullable|string',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
        ]);

        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'product_id' => $validated['product_id'],
            'sku' => $validated['sku'],
            'sales_keywords' => $validated['sales_keywords'],
            'transaction_keywords' => $validated['transaction_keywords'],
            'lead_time' => $validated['lead_time'] ?? 0,
            'unfulfilled_count' => $validated['unfulfilled_count'] ?? 0,
            'three_days_average' => $validated['three_days_average'] ?? 0,
        ]);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:255',
            'sales_keywords' => 'nullable|string',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
        ]);

        $item->update($validated);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Inventory Items record updated.');
    }

    public function destroy(Workspace $workspace, InventoryItem $item)
    {
        $item->delete();

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug);
    }
}
