<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncRun;
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

            // The entry's sync_run_id is the run we opened for this item on dispatch.
            GencysSyncRun::succeedById($workspace->id, $this->syncRunId($entry), count($rows), $saved);

            $results[] = [
                'inventory_item_id' => $item->id,
                'transactions_received' => count($rows),
                'transactions_saved' => $saved,
                'status' => 'synced',
            ];
        }

        return response()->json(['data' => $results]);
    }

    /** Pull the sync run id n8n echoed back, tolerating a couple of key spellings. */
    private function syncRunId(array $entry): ?int
    {
        $id = $entry['sync_run_id'] ?? $entry['syncRunId'] ?? null;

        return ($id === null || $id === '') ? null : (int) $id;
    }

    /**
     * Persist one item's rows, deriving each new row's remaining_qty by chaining
     * forward from the previous transaction.
     *
     * To stay light on the database we don't reload/rewrite the whole history. We read
     * the item's latest transaction once as the running anchor, then process the incoming
     * rows oldest-first: a new row's remaining_qty = anchor.remaining_qty + the row's own
     * net movement (goods in − out − bad − lost; see InventoryTransaction::netMovement),
     * which carries a manually audited level forward. It's folded into firstOrCreate as a
     * create-only value, so existing rows are never touched (an audited remaining_qty
     * survives) and no extra UPDATE is issued per row. firstOrCreate matches on the whole
     * row, so an exact re-sync is a no-op. Returns the rows seen.
     *
     * Assumes synced rows are newer than the stored history (the ERP appends recent days);
     * backfilled older rows won't be re-levelled here — a manual audit triggers the full
     * recalculateActualStock() pass for that.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveTransactions(InventoryItem $item, array $rows): int
    {
        // Chain in chronological order so each new row builds on the one before it.
        usort($rows, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        // The item's latest transaction so far is the running balance to build on.
        $anchor = InventoryTransaction::where('inventory_item_id', $item->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $saved = 0;

        foreach ($rows as $row) {
            $remainingStock = (float) ($row['inventory_remaining_stock'] ?? 0);
            $movement = InventoryTransaction::netMovementFromRow($row);

            // New-row remaining_qty: prior balance + this row's net movement, carrying any
            // audited level forward. The first-ever row (no prior) seeds from the ERP stock.
            $remainingQty = $anchor
                ? (int) $anchor->remaining_qty + $movement
                : (int) round($remainingStock);

            $transaction = InventoryTransaction::firstOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'workspace_id' => $item->workspace_id,
                    'ref_no' => $row['ref_no'],
                    'date' => $row['date'] ?? null,
                    'po_qty_in' => (int) ($row['po_qty_in'] ?? 0),
                    'po_qty_out' => (int) ($row['po_qty_out'] ?? 0),
                    'rts_goods_in' => (int) ($row['rts_goods_in'] ?? 0),
                    'rts_goods_out' => (int) ($row['rts_goods_out'] ?? 0),
                    'rts_bad' => (int) ($row['rts_bad'] ?? 0),
                    'inventory_remaining_stock' => $remainingStock,
                ],
                ['remaining_qty' => $remainingQty],
            );

            // Advance the anchor to this row (its stored value, audited or computed).
            $anchor = $transaction;
            $saved++;
        }

        return $saved;
    }
}
