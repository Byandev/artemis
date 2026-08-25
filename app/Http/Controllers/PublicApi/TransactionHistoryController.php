<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncCallbackFields;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class TransactionHistoryController extends Controller
{
    /**
     * Receive ERP transaction history synced back by n8n for many inventory items at once.
     *
     * The body is { items: [ { id, sync_run_id, transactions: [...] } ] }, where
     * `id` is the inventory item id and `sync_run_id` is the run we opened on
     * dispatch and n8n echoes back so we can mark it done.
     */
    public function bulkSync(Request $request, BatchRunner $runner): JsonResponse
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

            // The entry's sync_run_id is the run we opened for this item on dispatch.
            GencysSyncRun::succeedById(
                $workspace->id,
                SyncCallbackFields::runId($entry, $request),
                count($rows),
                $saved,
                SyncCallbackFields::executionId($entry, $request),
            );

            $results[] = [
                'inventory_item_id' => $item->id,
                'transactions_received' => count($rows),
                'transactions_saved' => $saved,
                'status' => 'synced',
            ];
        }

        // Every run this callback covered is now resolved, so whichever batch
        // they belonged to can send its next group.
        $runner->tick();

        return response()->json(['data' => $results]);
    }

    /**
     * Persist one item's rows exactly as the ERP reports them — no running-balance
     * recalculation. Each row's remaining_qty is taken straight from the ERP's reported
     * stock (inventory_remaining_stock); we don't chain movements forward or read the
     * prior row.
     *
     * remaining_qty is folded into firstOrCreate as a create-only value and matches on the
     * whole row, so an exact re-sync is a no-op and existing rows (including one whose
     * remaining_qty was manually corrected) are never touched. Returns the rows seen.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveTransactions(InventoryItem $item, array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $remainingStock = (float) ($row['inventory_remaining_stock'] ?? 0);

            InventoryTransaction::updateOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'workspace_id' => $item->workspace_id,
                    'ref_no' => $row['ref_no'],
                    'number' => $row['number'] ?? null,
                    'date' => $row['date'] ?? null,
                ],
                [
                    'po_qty_in' => (int) ($row['po_qty_in'] ?? 0),
                    'po_qty_out' => (int) ($row['po_qty_out'] ?? 0),
                    'rts_goods_in' => (int) ($row['rts_goods_in'] ?? 0),
                    'rts_goods_out' => (int) ($row['rts_goods_out'] ?? 0),
                    'rts_bad' => (int) ($row['rts_bad'] ?? 0),
                    'inventory_remaining_stock' => $remainingStock,
                    'remaining_qty' => (int) round($remainingStock),
                ]
            );

            $saved++;
        }

        return $saved;
    }
}
