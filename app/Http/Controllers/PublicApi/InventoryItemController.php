<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryItemController extends Controller
{
    public function keywords(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $items = InventoryItem::where('workspace_id', $workspace->id)
            ->whereNotNull('sales_keywords')
            ->where('sales_keywords', '!=', '')
            ->select(['id', 'sales_keywords', 'transaction_keywords'])
            ->get()
            ->map(fn ($item) => [
                'inventory_item_id' => $item->id,
                'sales_keywords' => $item->sales_keywords,
                'transaction_keywords' => $item->transaction_keywords,
            ]);

        return response()->json($items);
    }

    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'total_orders' => ['sometimes', 'integer', 'min:0'],
            'unfulfilled_count' => ['required', 'integer', 'min:0'],
            'three_days_average' => ['required', 'numeric', 'min:0'],
            'remaining_qty' => ['sometimes', 'integer', 'min:0'],
        ]);

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where('id', $validated['inventory_item_id'])
            ->firstOrFail();

        $item->update([
            'unfulfilled_count' => $validated['unfulfilled_count'],
            'three_days_average' => $validated['three_days_average'],
            'remaining_qty' => $validated['remaining_qty'] ?? $item->remaining_qty,
        ]);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function syncTransactions(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'item_name' => ['required', 'string'],
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.ref_no' => ['required', 'string', 'max:255'],
            'transactions.*.po_qty_in' => ['required', 'integer', 'min:0'],
            'transactions.*.po_qty_out' => ['required', 'integer', 'min:0'],
            'transactions.*.rts_goods_in' => ['required', 'integer', 'min:0'],
            'transactions.*.rts_goods_out' => ['required', 'integer', 'min:0'],
            'transactions.*.rts_bad' => ['required', 'integer', 'min:0'],
            'transactions.*.lost' => ['required', 'integer', 'min:0'],
            'transactions.*.remaining_qty' => ['required', 'integer', 'min:0'],
        ]);

        $itemName = $validated['item_name'];

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($itemName) {
                $q->where('transaction_keywords', 'like', "%{$itemName}%")
                    ->orWhere('sku', $itemName);
            })
            ->first();

        if (! $item) {
            return response()->json(['error' => "No inventory item matched for: {$itemName}"], 404);
        }

        $synced = 0;

        foreach ($validated['transactions'] as $row) {
            InventoryTransaction::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'inventory_item_id' => $item->id,
                    'ref_no' => $row['ref_no'],
                ],
                [
                    'date' => $row['date'],
                    'po_qty_in' => $row['po_qty_in'],
                    'po_qty_out' => $row['po_qty_out'],
                    'rts_goods_in' => $row['rts_goods_in'],
                    'rts_goods_out' => $row['rts_goods_out'],
                    'rts_bad' => $row['rts_bad'],
                    'lost' => $row['lost'],
                    'remaining_qty' => $row['remaining_qty'],
                ]
            );
            $synced++;
        }

        // Update remaining_qty on the item from the latest transaction
        $latestRemainingQty = InventoryTransaction::where('workspace_id', $workspace->id)
            ->where('inventory_item_id', $item->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('remaining_qty');

        if ($latestRemainingQty !== null) {
            $item->update(['remaining_qty' => $latestRemainingQty]);
        }

        return response()->json([
            'synced' => $synced,
            'remaining_qty' => $latestRemainingQty,
        ]);
    }
}
