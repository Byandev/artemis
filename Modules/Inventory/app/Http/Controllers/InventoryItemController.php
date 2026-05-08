<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $currentStocksSql = '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)';
        $waitingStocksSql = '(SELECT SUM(count) FROM inventory_purchased_order_items WHERE inventory_item_id = inventory_items.id AND EXISTS (SELECT * FROM inventory_purchased_orders WHERE inventory_purchased_order_items.inventory_purchased_order_id = inventory_purchased_orders.id AND status = 6))';
        $remainingAfterFulfillmentSql = "(COALESCE($currentStocksSql, 0) + COALESCE($waitingStocksSql, 0) - COALESCE(inventory_items.unfulfilled_count, 0))";
        $poNeededSql = "GREATEST(0, (COALESCE(inventory_items.lead_time, 0) * COALESCE(inventory_items.three_days_average, 0)) - COALESCE($waitingStocksSql, 0) - $remainingAfterFulfillmentSql)";
        $daysItCanLastSql = "(CASE WHEN inventory_items.three_days_average > 0 THEN $remainingAfterFulfillmentSql / inventory_items.three_days_average ELSE 0 END)";

        $items = QueryBuilder::for(InventoryItem::where('inventory_items.workspace_id', $workspace->id))
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            ->withSum('waitingForDeliveryItems as waiting_for_delivery_stocks', 'count')
            ->addSelect([
                'current_stocks' => InventoryTransaction::select('remaining_qty')
                    ->whereColumn('inventory_item_id', 'inventory_items.id')
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->limit(1),
            ])

            ->selectRaw("$remainingAfterFulfillmentSql as remaining_after_fulfillment")
            ->selectRaw("$poNeededSql as po_needed")
            ->selectRaw("$daysItCanLastSql as days_it_can_last")
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
                AllowedSort::field('product_name', 'products.name'),
                'lead_time',
                'unfulfilled_count',
                'three_days_average',
                'current_stocks',
                'waiting_for_delivery_stocks',
                AllowedSort::callback('remaining_after_fulfillment', function ($query, $descending) use ($remainingAfterFulfillmentSql) {
                    $query->orderByRaw("$remainingAfterFulfillmentSql ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('days_it_can_last', function ($query, $descending) use ($daysItCanLastSql) {
                    $query->orderByRaw("$daysItCanLastSql ".($descending ? 'DESC' : 'ASC'));
                }),
                AllowedSort::callback('po_needed', function ($query, $descending) use ($poNeededSql) {
                    $query->orderByRaw("$poNeededSql ".($descending ? 'DESC' : 'ASC'));
                }),
            ])
            ->defaultSort('-created_at')
            ->paginate((int) $request->input('per_page', 100))
            ->withQueryString();

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
        $this->authorize('Create Inventory Items', $workspace);

        $request->validate([
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
            'product_id' => $request->product_id,
            'sku' => $request->sku,
            'sales_keywords' => $request->sales_keywords,
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
        ]);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('inventory_items')
                    ->where('workspace_id', $workspace->id) 
                    ->ignore($item->id)                     
            ],
            'sales_keywords' => 'nullable|string',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
        ]);
        $item->update([
            'product_id' => $request->product_id,
            'sku' => $request->sku,
            'sales_keywords' => $request->sales_keywords,
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
        ]);

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', 'Inventory Items record updated.');
    }


    public function destroy(Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Delete Inventory Items', $workspace);

        $item->delete();

        return redirect()->route('workspaces.inventory.item.index', $workspace->slug);
    }
}