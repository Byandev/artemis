<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\N8nApi;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;
use Modules\Inventory\Models\InventoryItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The Gencys ERP sync queue: which batch holds the ERP right now, what's lined up
 * behind it, and how each one's runs went.
 *
 * Batches are system-wide — a scheduled one covers every ERP-connected workspace
 * — so the list isn't scoped to the current workspace. The runs inside a batch
 * are, since those are the records this workspace's people can act on.
 */
class SyncBatchController extends Controller
{
    public function __construct(private readonly SyncFlowRegistry $flows) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $batches = QueryBuilder::for(GencysSyncBatch::query()->with('createdBy:id,name'))
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('source'),
                // sync_types is a JSON list, so "batches covering X" is a
                // containment test rather than an equality one.
                AllowedFilter::callback(
                    'sync_type',
                    fn ($query, $value) => $query->whereJsonContains('sync_types', $value),
                ),
            ])
            ->allowedSorts(['id', 'status', 'queued_at', 'started_at', 'finished_at'])
            ->defaultSort('-id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $batches->through(fn (GencysSyncBatch $batch) => $this->present($batch));

        return Inertia::render('workspaces/gencys/sync-batches/index', [
            'workspace' => $workspace,
            'batches' => $batches,
            'syncTypes' => collect($this->flows->all())
                ->map(fn ($flow, $type) => ['value' => $type, 'label' => $flow->label()])
                ->values()
                ->all(),
            'queuedCount' => GencysSyncBatch::query()->queued()->count(),
            'running' => ($active = GencysSyncBatch::query()->running()->first())
                ? $this->present($active)
                : null,
            'itemCount' => InventoryItem::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->where('is_parent', false)
                ->count(),
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function show(Request $request, Workspace $workspace, GencysSyncBatch $batch): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $runs = QueryBuilder::for(
            $batch->runs()
                ->getQuery()
                ->where('workspace_id', $workspace->id)
                ->visibleTo($request->user(), $workspace)
                ->with('inventoryItem:id,product_id,sku')
        )
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('sync_type'),
            ])
            ->allowedSorts(['id', 'status', 'sync_type', 'sent_at', 'finished_at', 'attempt'])
            ->defaultSort('id')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        $runs->through(fn (GencysSyncRun $run) => [
            'id' => $run->id,
            'sync_type' => $run->sync_type,
            'sync_label' => $this->flows->has($run->sync_type)
                ? $this->flows->for($run->sync_type)->label()
                : $run->sync_type,
            'status' => $run->status,
            'attempt' => $run->attempt,
            'n8n_execution_id' => $run->n8n_execution_id,
            'group_key' => $run->group_key,
            'subject' => $run->inventoryItem?->sku ?? $this->subjectFromMeta($run),
            'rows_received' => $run->rows_received,
            'rows_saved' => $run->rows_saved,
            'sent_at' => $run->sent_at,
            'timeout_at' => $run->timeout_at,
            'finished_at' => $run->finished_at,
            'duration_seconds' => $run->sent_at && $run->finished_at
                ? $run->sent_at->diffInSeconds($run->finished_at)
                : null,
            'message' => $run->message,
        ]);

        return Inertia::render('workspaces/gencys/sync-batches/show', [
            'workspace' => $workspace,
            'batch' => $this->present($batch->load('createdBy:id,name')),
            'runs' => $runs,
            // Same per-run actions as the Sync Runs page: ask n8n how a run went,
            // and re-send a failed one when the queue is clear.
            'n8nApiConfigured' => N8nApi::make()->isConfigured(),
            'queueBusy' => GencysSyncBatch::query()->active()->exists(),
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Raise a batch by hand, pinned to this workspace. */
    public function store(Request $request, Workspace $workspace, BatchRunner $runner): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $validated = $request->validate([
            'sync_types' => ['required', 'array', 'min:1'],
            'sync_types.*' => ['string', 'distinct', 'in:'.implode(',', $this->flows->types())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'item_ids' => ['sometimes', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        $itemIds = $validated['item_ids'] ?? [];

        // One window, applied to whichever types were picked. Each still reads
        // its own dates the way its n8n flow expects.
        $parameters = collect($validated['sync_types'])
            ->mapWithKeys(fn (string $type) => [
                $type => $this->windowFor($type, $start, $end, $itemIds),
            ])
            ->all();

        $batch = $runner->queue(
            syncTypes: $validated['sync_types'],
            parameters: $parameters,
            workspaceId: $workspace->id,
            source: GencysSyncBatch::SOURCE_MANUAL,
            createdByUserId: $request->user()->id,
        );

        if (! $batch->wasRecentlyCreated) {
            return back()->with('warning', "An identical batch (#{$batch->id}) is already queued — nothing new was added.");
        }

        if ($batch->total_runs === 0) {
            return back()->with('error', 'Nothing to sync: this workspace has no ERP credentials, no API key, or no matching items.');
        }

        return back()->with('success', "Queued batch #{$batch->id} with {$batch->total_runs} run(s).");
    }

    public function cancel(Request $request, Workspace $workspace, GencysSyncBatch $batch, BatchRunner $runner): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        if ($batch->isFinished()) {
            return back()->with('warning', "Batch #{$batch->id} has already finished.");
        }

        $runner->cancel($batch, 'Cancelled by '.$request->user()->name.'.');

        return back()->with('success', "Cancelled batch #{$batch->id}.");
    }

    /**
     * Each flow reads its dates differently: purchase orders take one range, the
     * others take an explicit list of days.
     *
     * Only purchase orders can be narrowed to particular items — every other
     * flow asks the ERP for a date and takes back whatever is on the report.
     */
    private function windowFor(string $syncType, Carbon $start, Carbon $end, array $itemIds): array
    {
        if ($syncType === GencysSyncRun::TYPE_PURCHASE_ORDER) {
            return array_filter([
                'start_date' => $start->format('m/d/Y'),
                'end_date' => $end->format('m/d/Y'),
                'item_ids' => $itemIds,
            ]);
        }

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->format('m/d/Y');
        }

        return ['dates' => $dates];
    }

    /** @return array<string, mixed> */
    private function present(GencysSyncBatch $batch): array
    {
        $syncTypes = (array) $batch->sync_types;

        return [
            'id' => $batch->id,
            'sync_types' => $syncTypes,
            'sync_labels' => array_map(
                fn (string $type) => $this->flows->has($type) ? $this->flows->for($type)->label() : $type,
                $syncTypes,
            ),
            'status' => $batch->status,
            'source' => $batch->source,
            'workspace_id' => $batch->workspace_id,
            'created_by' => $batch->createdBy?->name,
            'parameters' => $batch->parameters,
            'group_size' => $batch->group_size,
            'timeout_seconds' => $batch->timeout_seconds,
            'max_retries' => $batch->max_retries,
            'total_runs' => $batch->total_runs,
            'succeeded_runs' => $batch->succeeded_runs,
            'failed_runs' => $batch->failed_runs,
            'cancelled_runs' => $batch->cancelled_runs,
            'progress' => $batch->progressPercent(),
            'message' => $batch->message,
            'queued_at' => $batch->queued_at,
            'started_at' => $batch->started_at,
            'finished_at' => $batch->finished_at,
        ];
    }

    /** Label a run that has no inventory item — its subject lives in the meta. */
    private function subjectFromMeta(GencysSyncRun $run): ?string
    {
        return data_get($run->meta, 'date')
            ?? data_get($run->meta, 'intern_id')
            ?? data_get($run->meta, 'page_id');
    }
}
