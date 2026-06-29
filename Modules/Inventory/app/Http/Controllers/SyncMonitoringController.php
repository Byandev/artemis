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
 * Monitoring for the ERP transaction-history and purchase-order syncs: a feed of
 * runs (with per-chunk success/failure), per-item coverage drawn from the latest
 * run, and a button to re-dispatch a run's failed chunks.
 */
class SyncMonitoringController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $runs = QueryBuilder::for(ErpSyncRun::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['started_at', 'finished_at', 'type', 'status', 'total_items', 'items_synced'])
            ->defaultSort('-started_at')
            ->withCount([
                'chunks as chunks_total',
                'chunks as chunks_sent' => fn ($q) => $q->whereIn('status', [ErpSyncChunk::STATUS_SENT, ErpSyncChunk::STATUS_CONFIRMED]),
                'chunks as chunks_failed' => fn ($q) => $q->where('status', ErpSyncChunk::STATUS_FAILED),
                'chunks as chunks_pending' => fn ($q) => $q->where('status', ErpSyncChunk::STATUS_PENDING),
            ])
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $runs->getCollection()->each(function ($run) {
            $run->duration_seconds = $run->started_at && $run->finished_at
                ? $run->started_at->diffInSeconds($run->finished_at)
                : null;
        });

        // Coverage: every active item with how it fared in the most recent run of
        // each type. Built by expanding the latest run's chunk item lists in PHP
        // (no JSON-column queries), so it works regardless of how n8n calls back.
        $latestTransactionRun = $this->latestRunWithChunks($workspace, ErpSyncRun::TYPE_TRANSACTION_HISTORY);
        $latestPurchaseOrderRun = $this->latestRunWithChunks($workspace, ErpSyncRun::TYPE_PURCHASE_ORDER);

        $transactionMap = $this->itemStatusMap($latestTransactionRun);
        $purchaseOrderMap = $this->itemStatusMap($latestPurchaseOrderRun);

        $activeItems = InventoryItem::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderBy('sku')
            ->get(['id', 'sku', 'remaining_qty']);

        $coverage = $activeItems->map(fn ($item) => [
            'id' => $item->id,
            'sku' => $item->sku,
            'remaining_qty' => $item->remaining_qty,
            'tx_status' => $transactionMap[$item->id]['status'] ?? null,
            'tx_synced_at' => $transactionMap[$item->id]['synced_at'] ?? null,
            'po_status' => $purchaseOrderMap[$item->id]['status'] ?? null,
            'po_synced_at' => $purchaseOrderMap[$item->id]['synced_at'] ?? null,
        ])->values();

        $synced = [ErpSyncChunk::STATUS_SENT, ErpSyncChunk::STATUS_CONFIRMED];
        $itemsNotSynced = $coverage->reject(fn ($c) => in_array($c['tx_status'], $synced, true))->count();

        return Inertia::render('workspaces/inventory/sync-monitoring/index', [
            'workspace' => $workspace,
            'runs' => $runs,
            'coverage' => $coverage,
            'stats' => [
                'active_items' => $activeItems->count(),
                'items_not_synced' => $itemsNotSynced,
                'failed_chunks' => ErpSyncChunk::where('workspace_id', $workspace->id)
                    ->where('status', ErpSyncChunk::STATUS_FAILED)
                    ->count(),
                'last_transaction_run' => $this->runSummary($latestTransactionRun),
                'last_purchase_order_run' => $this->runSummary($latestPurchaseOrderRun),
            ],
            'query' => [
                ...$request->only(['sort', 'page', 'perPage']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
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

    private function latestRunWithChunks(Workspace $workspace, string $type): ?ErpSyncRun
    {
        return ErpSyncRun::where('workspace_id', $workspace->id)
            ->where('type', $type)
            ->latest('started_at')
            ->with('chunks:id,gencys_erp_sync_run_id,status,item_ids,dispatched_at,confirmed_at')
            ->first();
    }

    /**
     * Map each inventory item id to the status and timestamp of the chunk that
     * carried it in the given run.
     *
     * @return array<int, array{status: string, synced_at: ?string}>
     */
    private function itemStatusMap(?ErpSyncRun $run): array
    {
        if (! $run) {
            return [];
        }

        $map = [];

        foreach ($run->chunks as $chunk) {
            $when = $chunk->confirmed_at ?? $chunk->dispatched_at;

            foreach ($chunk->item_ids ?? [] as $itemId) {
                $map[$itemId] = [
                    'status' => $chunk->status,
                    'synced_at' => $when?->toIso8601String(),
                ];
            }
        }

        return $map;
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
