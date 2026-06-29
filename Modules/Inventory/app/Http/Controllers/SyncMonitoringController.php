<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\GencysERP\Models\ErpSyncChunk;
use Modules\GencysERP\Models\ErpSyncRun;
use Modules\GencysERP\Services\ErpSyncService;
use Modules\Inventory\Models\InventoryItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Per-inventory-item monitoring for the ERP syncs: a board of every active item
 * with how recently it last synced (transactions and purchase orders) and how it
 * fared in the latest run, a drill-in to one item's full sync history, and retry
 * of a failed sync — at the run, chunk, or item level.
 */
class SyncMonitoringController extends Controller
{
    use AuthorizesRequests;

    /** An item with no successful transaction sync within this many days needs attention. */
    private const STALE_AFTER_DAYS = 3;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $staleThreshold = now()->subDays(self::STALE_AFTER_DAYS);

        // How each item fared in the most recent run of each type. Built by
        // expanding the latest run's chunk item lists (no JSON-column queries).
        $latestTransactionRun = $this->latestRunWithChunks($workspace, ErpSyncRun::TYPE_TRANSACTION_HISTORY);
        $latestPurchaseOrderRun = $this->latestRunWithChunks($workspace, ErpSyncRun::TYPE_PURCHASE_ORDER);

        $transactionMap = $this->latestRunItemMap($latestTransactionRun);
        $purchaseOrderMap = $this->latestRunItemMap($latestPurchaseOrderRun);

        $items = QueryBuilder::for(
            InventoryItem::where('workspace_id', $workspace->id)->where('is_active', true)
        )
            ->with('product:id,name')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('sku', 'like', "%{$value}%");
                }),
                AllowedFilter::callback('attention', function ($query, $value) use ($staleThreshold) {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    $query->where(function ($q) use ($staleThreshold) {
                        $q->whereNull('last_transaction_synced_at')
                            ->orWhere('last_transaction_synced_at', '<', $staleThreshold);
                    });
                }),
            ])
            ->allowedSorts(['sku', 'remaining_qty', 'last_transaction_synced_at', 'last_purchase_order_synced_at'])
            ->defaultSort('last_transaction_synced_at') // oldest/never-synced first — the ones needing attention
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $items->getCollection()->transform(function (InventoryItem $item) use ($transactionMap, $purchaseOrderMap, $staleThreshold) {
            $tx = $transactionMap[$item->id] ?? null;
            $po = $purchaseOrderMap[$item->id] ?? null;
            $stale = ! $item->last_transaction_synced_at || $item->last_transaction_synced_at->lt($staleThreshold);

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'product_name' => $item->product?->name,
                'remaining_qty' => $item->remaining_qty,
                'last_transaction_synced_at' => $item->last_transaction_synced_at?->toIso8601String(),
                'last_purchase_order_synced_at' => $item->last_purchase_order_synced_at?->toIso8601String(),
                'tx_latest_status' => $tx['status'] ?? null,   // null = not in the latest run
                'po_latest_status' => $po['status'] ?? null,
                'retryable_chunk_id' => $this->retryableChunkId($tx, $po),
                'is_stale' => $stale,
            ];
        });

        return Inertia::render('workspaces/inventory/sync-monitoring/index', [
            'workspace' => $workspace,
            'items' => $items,
            'recentRuns' => $this->recentRuns($workspace),
            'stats' => [
                'active_items' => InventoryItem::where('workspace_id', $workspace->id)->where('is_active', true)->count(),
                'stale_items' => InventoryItem::where('workspace_id', $workspace->id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($staleThreshold) {
                        $q->whereNull('last_transaction_synced_at')
                            ->orWhere('last_transaction_synced_at', '<', $staleThreshold);
                    })
                    ->count(),
                'failed_chunks' => ErpSyncChunk::where('workspace_id', $workspace->id)
                    ->where('status', ErpSyncChunk::STATUS_FAILED)
                    ->count(),
                'last_transaction_run' => $this->runSummary($latestTransactionRun),
                'last_purchase_order_run' => $this->runSummary($latestPurchaseOrderRun),
                'stale_after_days' => self::STALE_AFTER_DAYS,
            ],
            'query' => [
                ...$request->only(['sort', 'page', 'perPage']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * One item's full sync history — every chunk that included it, newest first —
     * so you can see exactly when it synced, failed, or was retried.
     */
    public function show(Request $request, Workspace $workspace, InventoryItem $item)
    {
        $this->authorize('View Inventory Items', $workspace);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $item->load('product:id,name');

        $history = ErpSyncChunk::where('workspace_id', $workspace->id)
            ->whereJsonContains('item_ids', $item->id)
            ->with('run:id,type,trigger,started_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $history->getCollection()->transform(fn (ErpSyncChunk $chunk) => [
            'id' => $chunk->id,
            'run_id' => $chunk->gencys_erp_sync_run_id,
            'type' => $chunk->type,
            'trigger' => $chunk->run?->trigger,
            'status' => $chunk->status,
            'attempts' => $chunk->attempts,
            'item_count' => $chunk->item_count,
            'records_synced' => $chunk->items_synced,
            'webhook_status' => $chunk->webhook_status,
            'error_message' => $chunk->error_message,
            'started_at' => $chunk->run?->started_at?->toIso8601String(),
            'dispatched_at' => $chunk->dispatched_at?->toIso8601String(),
            'confirmed_at' => $chunk->confirmed_at?->toIso8601String(),
        ]);

        return Inertia::render('workspaces/inventory/sync-monitoring/item', [
            'workspace' => $workspace,
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'product_name' => $item->product?->name,
                'remaining_qty' => $item->remaining_qty,
                'is_active' => $item->is_active,
                'last_transaction_synced_at' => $item->last_transaction_synced_at?->toIso8601String(),
                'last_purchase_order_synced_at' => $item->last_purchase_order_synced_at?->toIso8601String(),
            ],
            'history' => $history,
            'query' => [
                ...$request->only(['page', 'perPage']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
            ],
        ]);
    }

    /**
     * Re-dispatch every failed chunk on a run. Idempotent: the ERP callbacks
     * upsert, so re-syncing rows that already arrived is a no-op.
     */
    public function retryRun(Request $request, Workspace $workspace, ErpSyncRun $run, ErpSyncService $sync)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        abort_unless($run->workspace_id === $workspace->id, 404);

        $failed = $run->chunks()->where('status', ErpSyncChunk::STATUS_FAILED)->get();

        foreach ($failed as $chunk) {
            $sync->retryChunk($chunk);
        }

        return back()->with('success', $failed->isEmpty()
            ? 'No failed chunks to retry.'
            : "Re-queued {$failed->count()} failed chunk(s).");
    }

    /** Re-dispatch a single chunk (used by an item's history and the board retry button). */
    public function retryChunk(Request $request, Workspace $workspace, ErpSyncChunk $chunk, ErpSyncService $sync)
    {
        $this->authorize('Edit Inventory Items', $workspace);

        abort_unless($chunk->workspace_id === $workspace->id, 404);

        $sync->retryChunk($chunk);

        return back()->with('success', 'Sync re-queued.');
    }

    private function latestRunWithChunks(Workspace $workspace, string $type): ?ErpSyncRun
    {
        return ErpSyncRun::where('workspace_id', $workspace->id)
            ->where('type', $type)
            ->latest('started_at')
            ->with('chunks:id,gencys_erp_sync_run_id,status,item_ids')
            ->first();
    }

    /**
     * Map each inventory item id to the status and chunk of the chunk that
     * carried it in the given run.
     *
     * @return array<int, array{status: string, chunk_id: int}>
     */
    private function latestRunItemMap(?ErpSyncRun $run): array
    {
        if (! $run) {
            return [];
        }

        $map = [];

        foreach ($run->chunks as $chunk) {
            foreach ($chunk->item_ids ?? [] as $itemId) {
                $map[$itemId] = ['status' => $chunk->status, 'chunk_id' => $chunk->id];
            }
        }

        return $map;
    }

    /**
     * The chunk id to retry for an item: the failed chunk from its latest run
     * (transactions preferred, then purchase orders), or null if neither failed.
     */
    private function retryableChunkId(?array $tx, ?array $po): ?int
    {
        foreach ([$tx, $po] as $entry) {
            if (($entry['status'] ?? null) === ErpSyncChunk::STATUS_FAILED) {
                return $entry['chunk_id'];
            }
        }

        return null;
    }

    private function recentRuns(Workspace $workspace): array
    {
        return ErpSyncRun::where('workspace_id', $workspace->id)
            ->withCount([
                'chunks as chunks_total',
                'chunks as chunks_failed' => fn ($q) => $q->where('status', ErpSyncChunk::STATUS_FAILED),
                'chunks as chunks_pending' => fn ($q) => $q->where('status', ErpSyncChunk::STATUS_PENDING),
            ])
            ->latest('started_at')
            ->limit(8)
            ->get()
            ->map(fn (ErpSyncRun $run) => [
                'id' => $run->id,
                'type' => $run->type,
                'status' => $run->status,
                'total_items' => $run->total_items,
                'items_synced' => $run->items_synced,
                'chunks_total' => $run->chunks_total,
                'chunks_failed' => $run->chunks_failed,
                'chunks_pending' => $run->chunks_pending,
                'started_at' => $run->started_at?->toIso8601String(),
            ])
            ->all();
    }

    private function runSummary(?ErpSyncRun $run): ?array
    {
        if (! $run) {
            return null;
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'items_synced' => $run->items_synced,
            'total_items' => $run->total_items,
        ];
    }
}
