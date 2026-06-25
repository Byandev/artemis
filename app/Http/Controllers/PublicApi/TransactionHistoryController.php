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
     * Receive ERP transaction history synced back by n8n for many inventory items at once.
     *
     * One call carries many items. The body is a bare JSON array of
     * { id, transactions: [...] } objects, where `id` is the inventory item id.
     * Each item is processed exactly like the single-item sync.
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        // Accept the bare array body, or an { items: [...] } wrapper.
        $entries = $request->array('items', []);

        // Resolve every referenced item once, scoped to the authenticated workspace.
        $ids = collect($entries)->pluck('id')->filter()->unique()->all();

        $itemsById = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($entries as $entry) {
            $itemId = $entry['id'] ?? null;
            $rows = is_array($entry['transactions'] ?? null) ? $entry['transactions'] : [];
            $item = $itemId ? $itemsById->get($itemId) : null;

            if (! $item) {
                $results[] = [
                    'inventory_item_id' => $itemId,
                    'status' => 'skipped',
                    'reason' => 'not found in workspace',
                ];

                continue;
            }

            $saved = $this->saveTransactions($item, $rows);

            $results[] = [
                'inventory_item_id' => $item->id,
                'transactions_received' => count($rows),
                'transactions_saved' => $saved,
                'status' => 'synced',
            ];
        }

        return response()->json(['data' => $results]);
    }

    /**
     * Persist one item's rows, then re-level its actual stock from the audit anchors.
     *
     * firstOrCreate matches on the whole row: since ref_no alone isn't unique (it's the
     * ERP "Transact By" name), rows that differ in any field are kept as distinct records,
     * while an exact re-sync of the same row is a no-op. Existing rows are never touched,
     * so an audited remaining_qty survives. Returns the rows seen.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveTransactions(InventoryItem $item, array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $transaction = InventoryTransaction::firstOrCreate([
                'inventory_item_id' => $item->id,
                'workspace_id' => $item->workspace_id,
                'ref_no' => $row['ref_no'],
                'date' => $row['date'] ?? null,
                'po_qty_in' => (int) ($row['po_qty_in'] ?? 0),
                'po_qty_out' => (int) ($row['po_qty_out'] ?? 0),
                'rts_goods_in' => (int) ($row['rts_goods_in'] ?? 0),
                'rts_goods_out' => (int) ($row['rts_goods_out'] ?? 0),
                'rts_bad' => (int) ($row['rts_bad'] ?? 0),
                'inventory_remaining_stock' => (float) ($row['inventory_remaining_stock'] ?? 0),
            ]);

            if (! $transaction->remaining_qty) {
                $transaction->update(['remaining_qty' => $transaction->inventory_remaining_stock]);
            }

            $saved++;
        }

        return $saved;
    }
}
