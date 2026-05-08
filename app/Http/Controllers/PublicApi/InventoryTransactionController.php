<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryTransactionController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'ref_no' => ['required', 'string', 'max:255'],
            'po_qty_in' => ['required', 'integer', 'min:0'],
            'po_qty_out' => ['required', 'integer', 'min:0'],
            'rts_goods_in' => ['required', 'integer', 'min:0'],
            'rts_goods_out' => ['required', 'integer', 'min:0'],
            'rts_bad' => ['required', 'integer', 'min:0'],
            'lost' => ['required', 'integer', 'min:0'],
            'remaining_qty' => ['required', 'integer', 'min:0'],
        ]);

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($validated) {
                $q->where('sku', $validated['item_name'])
                    ->orWhere('transaction_keywords', 'like', "%{$validated['item_name']}%");
            })
            ->first();

        if (! $item) {
            return response()->json([
                'error' => "No inventory item matched for: {$validated['item_name']}",
            ], 404);
        }

        $transaction = InventoryTransaction::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'key' => $validated['key'],
            ],
            [
                'inventory_item_id' => $item->id,
                'date' => $validated['date'],
                'ref_no' => $validated['ref_no'],
                'po_qty_in' => $validated['po_qty_in'],
                'po_qty_out' => $validated['po_qty_out'],
                'rts_goods_in' => $validated['rts_goods_in'],
                'rts_goods_out' => $validated['rts_goods_out'],
                'rts_bad' => $validated['rts_bad'],
                'lost' => $validated['lost'],
                'remaining_qty' => $validated['remaining_qty'],
            ]
        );

        // Update remaining_qty on the item from its latest transaction
        $latestRemainingQty = InventoryTransaction::where('workspace_id', $workspace->id)
            ->where('inventory_item_id', $item->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('remaining_qty');

        if ($latestRemainingQty !== null) {
            $item->update(['remaining_qty' => $latestRemainingQty]);
        }

        return response()->json([
            'synced' => true,
            'transaction_id' => $transaction->id,
        ]);
    }
}