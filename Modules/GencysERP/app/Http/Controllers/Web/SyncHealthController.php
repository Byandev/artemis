<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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

        // Active items are exactly the ones the sync commands fetch.
        $items = InventoryItem::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->with('product:id,name')
            ->orderBy('sku')
            ->get(['id', 'product_id', 'sku']);

        $itemIds = $items->pluck('id')->all();

        // Latest run per (item, sync_type) for the summary grid.
        $latest = collect();

        if (! empty($itemIds)) {
            $latest = GencysSyncRun::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('inventory_item_id', $itemIds)
                ->whereIn('id', function ($q) use ($workspace, $itemIds) {
                    $q->from('gencys_sync_runs')
                        ->selectRaw('MAX(id)')
                        ->where('workspace_id', $workspace->id)
                        ->whereIn('inventory_item_id', $itemIds)
                        ->groupBy('inventory_item_id', 'sync_type');
                })
                ->get(['inventory_item_id', 'sync_type', 'status', 'rows_received', 'rows_saved', 'started_at', 'finished_at', 'message']);
        }

        $byItem = $latest->groupBy('inventory_item_id');

        $summary = [];

        foreach ($items as $item) {
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

            $summary[] = [
                'item' => [
                    'id' => (string) $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product?->name,
                ],
                'syncs' => $syncs,
            ];
        }

        $itemsById = $items->keyBy('id');

        // Recent runs — paginated, filterable feed.
        $recent = QueryBuilder::for(
            GencysSyncRun::query()->where('workspace_id', $workspace->id)
        )
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('sync_type'),
                AllowedFilter::exact('inventory_item_id'),
            ])
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
        $base = fn () => GencysSyncRun::query()->where('workspace_id', $workspace->id);

        $totalRuns24h = $base()->where('started_at', '>=', $since)->count();
        $successRuns24h = $base()->where('status', GencysSyncRun::STATUS_SUCCESS)->where('started_at', '>=', $since)->count();
        $failedRuns24h = $base()->where('status', GencysSyncRun::STATUS_FAILED)->where('started_at', '>=', $since)->count();
        $pendingRuns = $base()->where('status', GencysSyncRun::STATUS_PENDING)->count();

        $lastSuccessfulRun = $base()
            ->where('status', GencysSyncRun::STATUS_SUCCESS)
            ->latest('started_at')
            ->first(['sync_type', 'inventory_item_id', 'started_at']);

        return Inertia::render('workspaces/inventory/sync-health/index', [
            'workspace' => $workspace,
            'summary' => $summary,
            'syncTypes' => self::SYNC_TYPES,
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
        ]);
    }
}
