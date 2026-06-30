<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemExport;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryUnitCodeItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemController extends Controller
{
    use AuthorizesRequests;

    /**
     * Build the inventory-items query with the same computed columns, filters
     * and sorts the list view uses, so the export mirrors exactly what the
     * table shows. Returns the QueryBuilder un-paginated.
     */
    private function buildQuery(Request $request, Workspace $workspace): QueryBuilder
    {
        $currentStocksSql = $workspace->inventory_sync || true
            ? '(SELECT remaining_qty FROM inventory_transactions WHERE inventory_item_id = inventory_items.id ORDER BY date DESC, id DESC LIMIT 1)'
            : 'inventory_items.remaining_qty';

        // "Waiting for delivery" = the quantity still OWED on orders that are
        // awaiting delivery (status 6) — i.e. the undelivered remainder per item
        // (ordered count minus what has already been delivered), not the full
        // ordered count. Fully-delivered lines contribute 0; NULLIF keeps items
        // with nothing outstanding showing as "—" rather than 0.
        $waitingStocksSql = '(SELECT NULLIF(SUM(GREATEST(0, poi.count - COALESCE((SELECT SUM(d.qty) FROM inventory_purchased_order_item_deliveries d WHERE d.inventory_purchased_order_item_id = poi.id), 0))), 0) FROM inventory_purchased_order_items poi WHERE poi.inventory_item_id = inventory_items.id AND EXISTS (SELECT 1 FROM inventory_purchased_orders po WHERE poi.inventory_purchased_order_id = po.id AND po.status not in (7,8)))';
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

        return QueryBuilder::for($base)
            ->leftJoin('products', 'products.id', '=', 'inventory_items.product_id')
            ->select('inventory_items.*')
            ->with(['product'])
            // Undelivered remainder on status-6 orders (see $waitingStocksSql).
            ->selectRaw("$waitingStocksSql as waiting_for_delivery_stocks")
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
                // is_active is applied manually to $base above; register it as a
                // no-op here so QueryBuilder doesn't reject the filter key.
                AllowedFilter::callback('is_active', function () {}),
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
            ->defaultSort('-created_at');
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $items = $this->buildQuery($request, $workspace)
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

    public function export(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $filename = 'inventory-items-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new InventoryItemExport($this->buildQuery($request, $workspace)), $filename);
    }

    public function syncFromGencys(Workspace $workspace)
    {
        $this->authorize('Create Inventory Items', $workspace);

        abort_unless($workspace->is_gencys_partner, 403);

        $codes = InventoryUnitCodeItem::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->distinct()
            ->pluck('item_code');

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
