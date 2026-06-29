<?php

namespace Modules\GencysERP\Services;

use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Jobs\FetchInventoryItemPurchaseOrders;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
use Modules\GencysERP\Models\ErpSyncChunk;
use Modules\GencysERP\Models\ErpSyncRun;
use Modules\Inventory\Models\InventoryItem;

/**
 * Owns the dispatch side of the ERP sync: it chunks a workspace's active
 * inventory items, records a run + a chunk per job, and fires the n8n webhook
 * jobs. The commands call it to start a run; the monitoring page calls
 * retryChunk() to re-dispatch a failed chunk. Centralising payload-building here
 * keeps the trigger and the retry byte-for-byte identical.
 */
class ErpSyncService
{
    public const CHUNK_SIZE = 10;

    /**
     * Start a transaction-history run for one workspace and dispatch a staggered
     * job per chunk of its active items. Returns null when the workspace has no
     * API key, no webhook configured, or no active items.
     */
    public function startTransactionHistoryRun(
        Workspace $workspace,
        Carbon $date,
        string $trigger = ErpSyncRun::TRIGGER_SCHEDULE,
        ?int $triggeredBy = null,
        ?string $webhookOverride = null,
        int $delaySeconds = 10,
    ): ?ErpSyncRun {
        $webhookUrl = $webhookOverride ?: config('services.n8n.transaction_history_webhook_url');
        $apiKey = $workspace->apiKeys()->first();
        $items = $this->activeItems($workspace);

        if (! $webhookUrl || ! $apiKey || $items->isEmpty()) {
            return null;
        }

        $transactionDate = $date->format('m/d/Y');

        $run = ErpSyncRun::create([
            'workspace_id' => $workspace->id,
            'type' => ErpSyncRun::TYPE_TRANSACTION_HISTORY,
            'status' => ErpSyncRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'triggered_by' => $triggeredBy,
            'params' => ['date' => $transactionDate],
            'total_items' => $items->count(),
            'started_at' => now(),
        ]);

        $index = 0;

        $items->chunk(self::CHUNK_SIZE)->values()->each(function (Collection $chunk) use (&$index, $run, $workspace, $apiKey, $transactionDate, $webhookUrl, $delaySeconds) {
            $index++;

            $record = $this->createChunk($run, $workspace, $chunk);
            $data = $this->buildTransactionPayload($workspace, $apiKey, $chunk, $transactionDate, $record->id);

            dispatch(new FetchInventoryItemTransactionHistory($webhookUrl, $data, $record->id))
                ->delay(now()->addSeconds($index * $delaySeconds));
        });

        $run->update(['total_chunks' => $index]);

        return $run;
    }

    /**
     * Start a purchase-order run for one workspace. Mirrors the transaction run
     * but carries the PO date range and the already-delivered PO numbers n8n
     * uses to skip closed orders, and staggers by minutes (the ERP PO endpoint
     * is heavier).
     */
    public function startPurchaseOrderRun(
        Workspace $workspace,
        Carbon $startDate,
        Carbon $endDate,
        string $trigger = ErpSyncRun::TRIGGER_SCHEDULE,
        ?int $triggeredBy = null,
        ?string $webhookOverride = null,
    ): ?ErpSyncRun {
        $webhookUrl = $webhookOverride ?: config('services.n8n.purchase_order_webhook_url');
        $apiKey = $workspace->apiKeys()->first();
        $items = $this->activeItems($workspace);

        if (! $webhookUrl || ! $apiKey || $items->isEmpty()) {
            return null;
        }

        $startFormatted = $startDate->format('m/d/Y');
        $endFormatted = $endDate->format('m/d/Y');

        $run = ErpSyncRun::create([
            'workspace_id' => $workspace->id,
            'type' => ErpSyncRun::TYPE_PURCHASE_ORDER,
            'status' => ErpSyncRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'triggered_by' => $triggeredBy,
            'params' => ['start_date' => $startFormatted, 'end_date' => $endFormatted],
            'total_items' => $items->count(),
            'started_at' => now(),
        ]);

        $deliveredPoNumbers = $workspace->deliveredPurchaseOrders()->pluck('cust_po_no')->filter()->values()->all();

        $index = 0;

        $items->chunk(self::CHUNK_SIZE)->values()->each(function (Collection $chunk) use (&$index, $run, $workspace, $apiKey, $startFormatted, $endFormatted, $webhookUrl, $deliveredPoNumbers) {
            $index++;

            $record = $this->createChunk($run, $workspace, $chunk);
            $data = $this->buildPurchaseOrderPayload($workspace, $apiKey, $chunk, $startFormatted, $endFormatted, $deliveredPoNumbers, $record->id);

            dispatch(new FetchInventoryItemPurchaseOrders($webhookUrl, $data, $record->id))
                ->delay(now()->addMinutes($index * 3));
        });

        $run->update(['total_chunks' => $index]);

        return $run;
    }

    /**
     * Re-dispatch a single chunk's job, rebuilding the payload from its stored
     * item ids and its run's date params. Safe to call repeatedly: the ERP
     * callbacks upsert, so a re-sync of the same rows is a no-op.
     */
    public function retryChunk(ErpSyncChunk $chunk): void
    {
        $run = $chunk->run;
        $workspace = $chunk->workspace;

        if (! $run || ! $workspace) {
            return;
        }

        $apiKey = $workspace->apiKeys()->first();
        $items = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $chunk->item_ids ?? [])
            ->get();

        if (! $apiKey || $items->isEmpty()) {
            return;
        }

        // Reopen the chunk so the run rolls back to "running" until it settles.
        $chunk->forceFill(['status' => ErpSyncChunk::STATUS_PENDING, 'error_message' => null])->save();
        $run->recalculateStatus();

        if ($chunk->type === ErpSyncRun::TYPE_TRANSACTION_HISTORY) {
            $webhookUrl = config('services.n8n.transaction_history_webhook_url');
            $date = $run->params['date'] ?? Carbon::yesterday()->format('m/d/Y');
            $data = $this->buildTransactionPayload($workspace, $apiKey, $items, $date, $chunk->id);

            dispatch(new FetchInventoryItemTransactionHistory($webhookUrl, $data, $chunk->id));

            return;
        }

        $webhookUrl = config('services.n8n.purchase_order_webhook_url');
        $start = $run->params['start_date'] ?? Carbon::now()->subMonths(3)->format('m/d/Y');
        $end = $run->params['end_date'] ?? Carbon::today()->format('m/d/Y');
        $deliveredPoNumbers = $workspace->deliveredPurchaseOrders()->pluck('cust_po_no')->filter()->values()->all();
        $data = $this->buildPurchaseOrderPayload($workspace, $apiKey, $items, $start, $end, $deliveredPoNumbers, $chunk->id);

        dispatch(new FetchInventoryItemPurchaseOrders($webhookUrl, $data, $chunk->id));
    }

    /** Active inventory items for a workspace, freshly queried (retry-safe). */
    private function activeItems(Workspace $workspace): Collection
    {
        return $workspace->inventoryItems()->where('is_active', true)->get();
    }

    private function createChunk(ErpSyncRun $run, Workspace $workspace, Collection $items): ErpSyncChunk
    {
        return ErpSyncChunk::create([
            'gencys_erp_sync_run_id' => $run->id,
            'workspace_id' => $workspace->id,
            'type' => $run->type,
            'status' => ErpSyncChunk::STATUS_PENDING,
            'item_ids' => $items->pluck('id')->values()->all(),
            'item_count' => $items->count(),
        ]);
    }

    /** @param  Collection<int, InventoryItem>  $items */
    private function buildTransactionPayload(Workspace $workspace, WorkspaceApiKey $apiKey, Collection $items, string $transactionDate, int $chunkId): array
    {
        return [
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $apiKey->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'date' => $transactionDate,
            'webhook_url' => $this->callbackUrl('inventory-items/transactions/bulk-sync', $chunkId),
            'items' => $items->map(fn ($item) => ['id' => $item->id, 'keyword' => $item->sku])->values()->toArray(),
        ];
    }

    /** @param  Collection<int, InventoryItem>  $items */
    private function buildPurchaseOrderPayload(Workspace $workspace, WorkspaceApiKey $apiKey, Collection $items, string $startDate, string $endDate, array $deliveredPoNumbers, int $chunkId): array
    {
        return [
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $apiKey->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'webhook_url' => $this->callbackUrl('purchase-orders/bulk-sync', $chunkId),
            'items' => $items->map(fn ($item) => ['id' => $item->id, 'keyword' => $item->sku])->values()->toArray(),
            'delivered_purchase_orders_no' => $deliveredPoNumbers,
        ];
    }

    /**
     * Callback URL n8n posts results to, carrying the chunk id as a query param.
     * If n8n preserves the URL verbatim (it does by default) the callback can
     * confirm exactly which chunk landed — no n8n workflow change required.
     */
    private function callbackUrl(string $path, int $chunkId): string
    {
        $base = rtrim(config('app.url'), '/');

        return "{$base}/api/v1/public/{$path}?sync_chunk_id={$chunkId}";
    }
}
