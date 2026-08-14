<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Actions\RetryGencysSyncRuns;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Renders the per-inventory-item Gencys ERP sync health view: for every active
 * item it shows the latest transaction-history and purchase-order sync (status,
 * row counts, when, error), 24h KPIs, and a filterable feed of recent runs.
 */
class SyncHealthController extends Controller
{
    /** The per-item sync flows shown as columns, left to right. */
    private const SYNC_TYPES = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        // All active items — used to label recent runs and for the active-items KPI.
        // Parent items are placeholder groupings that never sync themselves, so
        // they're excluded from the item views.
        $items = InventoryItem::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('is_parent', false)
            ->visibleTo($request->user(), $workspace)
            ->with('product:id,name')
            ->orderBy('sku')
            ->get(['id', 'product_id', 'sku']);

        $itemsById = $items->keyBy('id');

        // Per-item status grid — searchable (SKU / product name) and paginated.
        $itemSearch = trim((string) $request->input('items_search', ''));

        $summary = InventoryItem::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('is_parent', false)
            ->visibleTo($request->user(), $workspace)
            ->when($itemSearch !== '', function ($query) use ($itemSearch) {
                $query->where(function ($q) use ($itemSearch) {
                    $q->where('sku', 'like', "%{$itemSearch}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$itemSearch}%"));
                });
            })
            ->with('product:id,name')
            ->orderBy('sku')
            ->paginate($request->integer('items_per_page', 25), ['id', 'product_id', 'sku'], 'items_page')
            ->withQueryString();

        // Latest run per (item, sync_type), limited to the items on the current page.
        $pageItemIds = $summary->getCollection()->pluck('id')->all();

        $byItem = collect();

        if (! empty($pageItemIds)) {
            $byItem = GencysSyncRun::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('inventory_item_id', $pageItemIds)
                ->whereIn('id', function ($q) use ($workspace, $pageItemIds) {
                    $q->from('gencys_sync_runs')
                        ->selectRaw('MAX(id)')
                        ->where('workspace_id', $workspace->id)
                        ->whereIn('inventory_item_id', $pageItemIds)
                        ->groupBy('inventory_item_id', 'sync_type');
                })
                ->get(['inventory_item_id', 'sync_type', 'status', 'rows_received', 'rows_saved', 'started_at', 'finished_at', 'message'])
                ->groupBy('inventory_item_id');
        }

        $summary->through(function ($item) use ($byItem) {
            $entries = collect($byItem[$item->id] ?? [])->keyBy('sync_type');

            $syncs = [];
            foreach (self::SYNC_TYPES as $type) {
                $row = $entries[$type] ?? null;
                $syncs[] = [
                    'sync_type' => $type,
                    'status' => $row->status ?? null,
                    'rows_received' => $row->rows_received ?? null,
                    'rows_saved' => $row->rows_saved ?? null,
                    'started_at' => $row->started_at ?? null,
                    'finished_at' => $row->finished_at ?? null,
                    'message' => $row->message ?? null,
                ];
            }

            return [
                'item' => [
                    'id' => (string) $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product?->name,
                ],
                'syncs' => $syncs,
            ];
        });

        // Recent runs — paginated, filterable feed.
        $recent = QueryBuilder::for(
            GencysSyncRun::query()->where('workspace_id', $workspace->id)
                ->visibleTo($request->user(), $workspace)
        )
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('sync_type'),
                AllowedFilter::exact('inventory_item_id'),
            ])
            ->whereIn('sync_type', [GencysSyncRun::TYPE_PURCHASE_ORDER, GencysSyncRun::TYPE_TRANSACTION_HISTORY])
            ->allowedSorts(['started_at', 'finished_at', 'rows_received', 'sync_type', 'status'])
            ->defaultSort('-started_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Tack item label + duration onto each row so the frontend renders directly.
        $recent->getCollection()->each(function ($run) use ($itemsById) {
            $item = $run->inventory_item_id ? $itemsById->get($run->inventory_item_id) : null;
            $run->item_sku = $item->sku ?? null;
            $run->item_name = $item?->product?->name;
            $run->duration_seconds = $run->started_at && $run->finished_at
                ? $run->started_at->diffInSeconds($run->finished_at)
                : null;
        });

        // 24h KPIs.
        $since = now()->subDay();
        $base = fn () => GencysSyncRun::query()
            ->where('workspace_id', $workspace->id)
            ->visibleTo($request->user(), $workspace);

        $totalRuns24h = $base()->where('started_at', '>=', $since)->count();
        $successRuns24h = $base()->where('status', GencysSyncRun::STATUS_SUCCESS)->where('started_at', '>=', $since)->count();
        $failedRuns24h = $base()->where('status', GencysSyncRun::STATUS_FAILED)->where('started_at', '>=', $since)->count();
        $pendingRuns = $base()->where('status', GencysSyncRun::STATUS_PENDING)->count();

        $lastSuccessfulRun = $base()
            ->where('status', GencysSyncRun::STATUS_SUCCESS)
            ->latest('started_at')
            ->first(['sync_type', 'inventory_item_id', 'started_at']);

        // The scheduled sweeps, newest first. `running` here is what makes the
        // next slot skip this workspace, so it's the first thing to look at when
        // a slot appears to have produced nothing.
        $batches = GencysSyncBatch::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (GencysSyncBatch $batch) => [
                'id' => $batch->id,
                'trigger' => $batch->trigger,
                'status' => $batch->status,
                'sync_types' => $batch->sync_types,
                'total_runs' => $batch->total_runs,
                'completed_runs' => $batch->completed_runs,
                'failed_runs' => $batch->failed_runs,
                'pending_runs' => max(0, $batch->total_runs - $batch->completed_runs - $batch->failed_runs),
                'message' => $batch->message,
                'started_at' => $batch->started_at,
                'finished_at' => $batch->finished_at,
                'duration_seconds' => $batch->started_at && $batch->finished_at
                    ? $batch->started_at->diffInSeconds($batch->finished_at)
                    : null,
                // Only a batch with unresolved runs has anything to replay.
                'can_retry' => $batch->status !== GencysSyncBatch::STATUS_SKIPPED
                    && ($batch->failed_runs > 0 || $batch->status === GencysSyncBatch::STATUS_RUNNING),
            ]);

        return Inertia::render('workspaces/inventory/sync-health/index', [
            'workspace' => $workspace,
            'summary' => $summary,
            'activeItemsCount' => $items->count(),
            'syncTypes' => self::SYNC_TYPES,
            'batches' => $batches,
            'canRetry' => $request->user()->can('Edit Inventory Items', $workspace),
            'n8nExecutionUrlPrefix' => $this->n8nExecutionUrlPrefix(),
            'recent' => $recent,
            'totalRuns24h' => $totalRuns24h,
            'successRuns24h' => $successRuns24h,
            'failedRuns24h' => $failedRuns24h,
            'pendingRuns' => $pendingRuns,
            'lastSuccessfulRun' => $lastSuccessfulRun ? [
                'sync_type' => $lastSuccessfulRun->sync_type,
                'item_sku' => $itemsById->get($lastSuccessfulRun->inventory_item_id)?->sku,
                'started_at' => $lastSuccessfulRun->started_at,
            ] : null,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
            'itemsQuery' => [
                'page' => $request->input('items_page'),
                'perPage' => $request->input('items_per_page'),
                'search' => $itemSearch !== '' ? $itemSearch : null,
            ],
        ]);
    }

    /**
     * Prefix that turns a run's stored n8n execution id into a deep link into
     * the n8n editor. Null when either config value is missing — the id is still
     * shown, just not clickable.
     */
    private function n8nExecutionUrlPrefix(): ?string
    {
        $base = config('services.n8n.base_url');
        $workflowId = config('services.n8n.gencys_workflow_id');

        if (! $base || ! $workflowId) {
            return null;
        }

        return rtrim($base, '/')."/workflow/{$workflowId}/executions/";
    }

    /** Replay every failed or still-outstanding run in a batch. */
    public function retryBatch(Request $request, Workspace $workspace, GencysSyncBatch $batch, RetryGencysSyncRuns $retry): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        // Route model binding resolves the batch by id alone, so confirm it
        // actually belongs to this workspace before replaying anything.
        abort_unless($batch->workspace_id === $workspace->id, 404);

        $count = $retry->forBatch($batch);

        return back()->with('success', $count > 0
            ? "Re-dispatched {$count} sync run(s) for batch #{$batch->id}."
            : "Batch #{$batch->id} had no runs to retry.");
    }

    /** Replay a single run from the recent-runs feed. */
    public function retryRun(Request $request, Workspace $workspace, GencysSyncRun $run, RetryGencysSyncRuns $retry): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($run->workspace_id === $workspace->id, 404);

        $count = $retry->forRun($run);

        return back()->with('success', $count > 0
            ? "Re-dispatched sync run #{$run->id}."
            : "Sync run #{$run->id} is a type that can't be retried from here.");
    }
}
