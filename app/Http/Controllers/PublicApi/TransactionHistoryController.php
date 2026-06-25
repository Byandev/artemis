<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class TransactionHistoryController extends Controller
{
    /**
     * Receive ERP transaction history synced back by n8n for a single inventory item.
     *
     * The trigger dispatches one n8n call per inventory item, so each callback is scoped
     * to one item via the route: `/inventory-items/{inventoryItem}/transactions/sync`. The
     * body holds the rows, each upserted by (inventory_item_id, unique_key).
     *
     * Each row:
     * {
     *   "date": "2026-06-24",
     *   "unique_key": "June 24, 2026|Rigor Esperanzate|Pikutin Harabas 2.0|OUT|1|0|0|1221",
     *   "po_qty_in": "0",
     *   "po_qty_out": "1",
     *   "rts_goods_in": "0",
     *   "rts_goods_out": "0",
     *   "rts_bad": "0",
     *   "inventory_remaining_stock": "1221"
     * }
     */
    public function sync(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $rows = $request->array('transactions', []);

        $saved = 0;

        foreach ($rows as $row) {
            $uniqueKey = $row['unique_key'] ?? null;

            if (! $uniqueKey) {
                continue;
            }

            InventoryTransaction::updateOrCreate(
                [
                    'inventory_item_id' => $inventoryItem->id,
                    'ref_no' => $uniqueKey,
                ],
                [
                    'workspace_id' => $inventoryItem->workspace_id,
                    'date' => $row['date'] ?? null,
                    'po_qty_in' => (int) ($row['po_qty_in'] ?? 0),
                    'po_qty_out' => (int) ($row['po_qty_out'] ?? 0),
                    'rts_goods_in' => (int) ($row['rts_goods_in'] ?? 0),
                    'rts_goods_out' => (int) ($row['rts_goods_out'] ?? 0),
                    'rts_bad' => (int) ($row['rts_bad'] ?? 0),
                    'inventory_remaining_stock' => (float) ($row['inventory_remaining_stock'] ?? 0),
                ],
            );

            $saved++;
        }

        return response()->json([
            'data' => [
                'inventory_item_id' => $inventoryItem->id,
                'transactions_received' => count($rows),
                'transactions_saved' => $saved,
                'status' => 'synced',
            ],
        ]);
    }
}
