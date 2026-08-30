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
use Modules\Inventory\Support\InventoryItemResolver;

class TransactionHistoryController extends Controller
{
    /**
     * Receive one date's ERP transaction history, as n8n scrapes it: every item
     * on the report, each with its rows.
     *
     * The body is the list itself, or an { items: [...] } / { data: [...] }
     * wrapper around it:
     *
     * [
     *   {
     *     "item": "Amazing Kidney Care Patch",
     *     "transactions": [
     *       { "number": 1, "date": "2026-08-30", "ref_no": "Anna Marie Mallo",
     *         "po_qty_in": "2,400", "po_qty_out": "0", "rts_goods_in": "0",
     *         "rts_goods_out": "0", "rts_bad": "0",
     *         "inventory_remaining_stock": "2,400" }
     *     ]
     *   }
     * ]
     *
     * Items arrive by name and are matched (or created) by InventoryItemResolver;
     * quantities arrive as formatted strings and are read as numbers.
     *
     * The whole callback belongs to a single sync run — the date's run, whose id
     * n8n echoes back as `sync_run_id` (in the body or the query string). Failing
     * that we fall back to the workspace's oldest transaction-history run still
     * in flight, which is unambiguous because a batch only ever has one date out
     * at a time.
     *
     * A report too big for one call can be posted in pieces: send `has_more`
     * (or `final: false`) on every piece but the last, and each one extends the
     * run's deadline instead of closing it. n8n may also close it explicitly via
     * the finish endpoint.
     *
     * The older per-item shape — { items: [{ id, sync_run_id, transactions }] },
     * one run per item — is still accepted, so a batch queued before this changed
     * still resolves its runs.
     */
    public function bulkSync(Request $request, BatchRunner $runner): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $entries = $this->entries($request);
        $resolver = new InventoryItemResolver($workspace->id);
        $executionId = SyncCallbackFields::executionId($request);

        $results = [];
        $received = 0;
        $saved = 0;

        // Set only by the legacy per-item shape; its presence is what tells the
        // two shapes apart below.
        $entryRunIds = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $rows = is_array($entry['transactions'] ?? null) ? $entry['transactions'] : [];
            $name = is_string($entry['item'] ?? null) ? $entry['item'] : null;

            // An `id` means the old shape, where we chose the item ourselves.
            $item = isset($entry['id'])
                ? InventoryItem::where('workspace_id', $workspace->id)->find($entry['id'])
                : $resolver->resolve($name);

            if (! $item) {
                $results[] = [
                    'item' => $name ?? ($entry['id'] ?? null),
                    'status' => 'skipped',
                    'reason' => 'no item to attach these transactions to',
                ];

                continue;
            }

            $rowsSaved = $this->saveTransactions($item, array_filter($rows, 'is_array'));

            $received += count($rows);
            $saved += $rowsSaved;

            $results[] = [
                'item' => $item->sku,
                'inventory_item_id' => $item->id,
                'created' => $resolver->wasCreated($item),
                'transactions_received' => count($rows),
                'transactions_saved' => $rowsSaved,
                'status' => 'synced',
            ];

            if ($runId = SyncCallbackFields::runId($entry)) {
                $entryRunIds[] = $runId;

                GencysSyncRun::succeedById($workspace->id, $runId, count($rows), $rowsSaved, $executionId);
            }
        }

        $run = empty($entryRunIds)
            ? $this->resolveRun($request, $workspace->id, $received, $saved, $executionId)
            : null;

        // Every run this callback covered is now resolved, so whichever batch
        // they belonged to can send its next group.
        $runner->tick();

        return response()->json([
            'data' => $results,
            'sync_run_id' => $run?->id ?? ($entryRunIds[0] ?? null),
            'transactions_received' => $received,
            'transactions_saved' => $saved,
            'items_created' => count($resolver->createdItems()),
        ]);
    }

    /**
     * The entries this callback carries, whichever way they were wrapped.
     *
     * n8n workflows post a bare array as readily as a keyed object, and the key
     * they choose is whatever the node was named, so all three shapes are read
     * rather than made someone's problem to get right.
     *
     * @return array<int, array<string, mixed>>
     */
    private function entries(Request $request): array
    {
        foreach (['items', 'data'] as $key) {
            if (is_array($value = $request->input($key))) {
                return $value;
            }
        }

        // A bare array body is the list itself. It has to be read off the JSON
        // source rather than all(), which merges the query string in and so
        // stops the body looking like a list at all.
        $body = $request->isJson() ? $request->json()->all() : $request->all();

        return array_is_list($body) ? $body : [];
    }

    /**
     * Credit this callback to the run it belongs to and, unless more is coming,
     * close it.
     *
     * The counts are always fed in as a heartbeat first so a chunked report adds
     * up instead of the last piece overwriting the total; closing then takes the
     * accumulated figures.
     */
    private function resolveRun(
        Request $request,
        int $workspaceId,
        int $received,
        int $saved,
        ?string $executionId,
    ): ?GencysSyncRun {
        $runId = SyncCallbackFields::runId($request)
            ?? GencysSyncRun::oldestInFlight($workspaceId, GencysSyncRun::TYPE_TRANSACTION_HISTORY)?->id;

        if (! $runId) {
            return null;
        }

        $run = GencysSyncRun::heartbeatById($workspaceId, $runId, $received, $saved, $executionId);

        if ($this->expectsMore($request)) {
            return $run;
        }

        return GencysSyncRun::finishById($workspaceId, $runId, executionId: $executionId) ?? $run;
    }

    /** Whether n8n says this is one piece of a report still being posted. */
    private function expectsMore(Request $request): bool
    {
        if ($request->has('has_more')) {
            return $request->boolean('has_more');
        }

        foreach (['final', 'is_final', 'last_chunk'] as $key) {
            if ($request->has($key)) {
                return ! $request->boolean($key);
            }
        }

        return false;
    }

    /**
     * Persist one item's rows exactly as the ERP reports them — no running-balance
     * recalculation. Each row's remaining_qty is taken straight from the ERP's reported
     * stock (inventory_remaining_stock); we don't chain movements forward or read the
     * prior row.
     *
     * The row is keyed on the item, its ref_no, its number within the day and its
     * date, so re-syncing a date the ERP has since corrected updates the row in
     * place rather than laying a second copy beside it — which matters now that
     * every pass brings back the whole day rather than only what we asked for.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveTransactions(InventoryItem $item, array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $remainingStock = self::number($row['inventory_remaining_stock'] ?? 0);

            InventoryTransaction::updateOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'workspace_id' => $item->workspace_id,
                    'ref_no' => $row['ref_no'] ?? null,
                    'number' => $row['number'] ?? null,
                    'date' => $row['date'] ?? null,
                ],
                [
                    'po_qty_in' => (int) self::number($row['po_qty_in'] ?? 0),
                    'po_qty_out' => (int) self::number($row['po_qty_out'] ?? 0),
                    'rts_goods_in' => (int) self::number($row['rts_goods_in'] ?? 0),
                    'rts_goods_out' => (int) self::number($row['rts_goods_out'] ?? 0),
                    'rts_bad' => (int) self::number($row['rts_bad'] ?? 0),
                    'inventory_remaining_stock' => $remainingStock,
                    'remaining_qty' => (int) round($remainingStock),
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * A quantity as the ERP writes it — "1,249" is a number, not a string with a
     * comma in it, and PHP's cast would read it as 1.
     */
    private static function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return (float) str_replace([',', ' ', "\u{00a0}"], '', (string) $value);
    }
}
