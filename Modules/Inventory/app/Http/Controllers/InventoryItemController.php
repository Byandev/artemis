<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\GencysERP\Models\GencysUnitCodeInventoryItem;
use Modules\Inventory\Models\InventoryItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $currentStocksSql = $workspace->inventory_sync || true
            ? '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)'
            : 'inventory_items.remaining_qty';

        $waitingStocksSql = '(SELECT SUM(count) FROM inventory_purchased_order_items WHERE inventory_item_id = inventory_items.id AND EXISTS (SELECT * FROM inventory_purchased_orders WHERE inventory_purchased_order_items.inventory_purchased_order_id = inventory_purchased_orders.id AND status = 6))';
        $remainingAfterFulfillmentSql = "(COALESCE($currentStocksSql, 0) + COALESCE($waitingStocksSql, 0) - COALESCE(inventory_items.unfulfilled_count, 0))";
        $poNeededSql = "GREATEST(0, (COALESCE(inventory_items.lead_time, 0) * COALESCE(inventory_items.three_days_average, 0)) - COALESCE($waitingStocksSql, 0) - $remainingAfterFulfillmentSql)";
        $daysItCanLastSql = "(CASE WHEN inventory_items.three_days_average > 0 THEN $remainingAfterFulfillmentSql / inventory_items.three_days_average ELSE 0 END)";

        // The list defaults to active items only. `filter[is_active]=all` shows every
        // item; an explicit 0/1 narrows to inactive/active.
        $isActiveFilter = $request->input('filter.is_active');

        $base = InventoryItem::where('inventory_items.workspace_id', $workspace->id);

        if ($isActiveFilter === null) {
            $base->where('inventory_items.is_active', true);
        } elseif ($isActiveFilter !== 'all') {
            $base->where('inventory_items.is_active', filter_var($isActiveFilter, FILTER_VALIDATE_BOOLEAN));
        }

        $items = QueryBuilder::for($base)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            ->withSum('waitingForDeliveryItems as waiting_for_delivery_stocks', 'count')
            ->selectRaw("$currentStocksSql as current_stocks")
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
                'is_active',
                AllowedSort::field('product_name', 'products.name'),
                'lead_time',
                'unfulfilled_count',
                'remaining_qty',
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

    public function syncFromGencys(Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        abort_unless($workspace->is_gencys_partner, 403);

        $codes = GencysUnitCodeInventoryItem::query()
            ->whereHas('unitCode', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->whereNotNull('inventory_item_code')
            ->where('inventory_item_code', '!=', '')
            ->distinct()
            ->pluck('inventory_item_code');

        $created = 0;

        foreach ($codes as $code) {
            // Match on (workspace_id, sku); don't touch product_id on existing
            // items so a manually linked product survives re-syncs. New items get
            // a null product_id from the column default.
            $item = InventoryItem::updateOrCreate(
                ['workspace_id' => $workspace->id, 'sku' => $code],
            );

            if ($item->wasRecentlyCreated) {
                $created++;
            }
        }

        return redirect()
            ->route('workspaces.inventory.item.index', $workspace->slug)
            ->with('success', "Synced {$created} new inventory item(s) from Gencys.");
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'sku' => 'required|string|max:255|unique:inventory_items,sku,NULL,id,workspace_id,'.$workspace->id,
            'is_active' => 'nullable|boolean',
            'sales_keywords' => 'nullable|array',
            'sales_keywords.*' => 'string|max:255',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
            'remaining_qty' => 'nullable|integer',
        ]);

        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'product_id' => $request->product_id ?: null,
            'sku' => $request->sku,
            'is_active' => $request->boolean('is_active', true),
            'sales_keywords' => implode(', ', $this->normalizeKeywords($request->input('sales_keywords'))),
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
            'remaining_qty' => $request->remaining_qty,
        ]);

        // back() keeps the list's current filters/sort/page (they live in the URL).
        return redirect()->back()
            ->with('success', 'Items record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_items')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($item->id),
            ],
            'is_active' => 'nullable|boolean',
            'sales_keywords' => 'nullable|array',
            'sales_keywords.*' => 'string|max:255',
            'transaction_keywords' => 'nullable|string',
            'lead_time' => 'nullable|integer|min:0',
            'unfulfilled_count' => 'nullable|integer|min:0',
            'three_days_average' => 'nullable|numeric|min:0',
            'remaining_qty' => 'nullable|integer',
        ]);
        $item->update([
            'product_id' => $request->product_id ?: null,
            'sku' => $request->sku,
            'is_active' => $request->boolean('is_active', true),
            'sales_keywords' => implode(', ', $this->normalizeKeywords($request->input('sales_keywords'))),
            'transaction_keywords' => $request->transaction_keywords,
            'lead_time' => $request->lead_time ?? 0,
            'unfulfilled_count' => $request->unfulfilled_count ?? 0,
            'three_days_average' => $request->three_days_average ?? 0,
            'remaining_qty' => $request->remaining_qty,
        ]);

        return redirect()->back()
            ->with('success', 'Inventory Items record updated.');
    }

    /**
     * Activate or deactivate multiple inventory items at once.
     */
    public function bulkUpdateStatus(Request $request, Workspace $workspace)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'is_active' => 'required|boolean',
        ]);

        $updated = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['is_active' => $validated['is_active']]);

        $status = $validated['is_active'] ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "{$updated} inventory item(s) {$status}.");
    }

    /**
     * Split a comma-separated keyword string into a clean array:
     * trim, drop blanks, de-duplicate.
     *
     * @param  mixed  $keywords
     * @return string[]
     */
    private function normalizeKeywords($keywords): array
    {
        $list = is_array($keywords)
            ? $keywords
            : preg_split('/[,\n]+/', (string) $keywords);

        return collect($list)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function destroy(Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('Delete Inventory Items', $workspace);

        $item->delete();

        return redirect()->back()
            ->with('success', 'Inventory Items record deleted.');
    }
}
