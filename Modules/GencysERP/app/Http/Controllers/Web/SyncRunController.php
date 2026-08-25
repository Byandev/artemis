<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\N8nApi;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/**
 * This workspace's Gencys ERP sync runs, grouped under the batch that sent them.
 *
 * Batches are the outer, paginated level; a batch's runs are only queried when
 * it's expanded, because a single batch can hold hundreds of them. Expanding is
 * a partial Inertia reload rather than a client-side toggle for the same reason.
 */
class SyncRunController extends Controller
{
    /** Runs shown inline per batch before we point at the batch page instead. */
    private const INLINE_RUN_LIMIT = 100;

    public function __construct(private readonly SyncFlowRegistry $flows) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $syncType = $request->string('sync_type')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $expanded = $this->expandedIds($request);

        // Only batches that actually did something for this workspace, and only
        // those still matching once the filters are applied.
        $batches = GencysSyncBatch::query()
            ->whereHas('runs', fn (Builder $query) => $this->scopeRuns($query, $request, $workspace, $syncType, $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $batchIds = $batches->getCollection()->pluck('id')->all();

        $counts = $this->countsByBatch($request, $workspace, $batchIds, $syncType, $status);
        $runs = $this->runsForExpanded($request, $workspace, array_intersect($expanded, $batchIds), $syncType, $status);

        $batches->through(fn (GencysSyncBatch $batch) => [
            ...$this->presentBatch($batch),
            'counts' => $counts[$batch->id] ?? [],
            'run_total' => array_sum($counts[$batch->id] ?? []),
            'runs' => $runs[$batch->id] ?? null,
            'runs_truncated' => isset($runs[$batch->id])
                && array_sum($counts[$batch->id] ?? []) > self::INLINE_RUN_LIMIT,
        ]);

        return Inertia::render('workspaces/gencys/sync-runs/index', [
            'workspace' => $workspace,
            'batches' => $batches,
            'syncTypes' => collect($this->flows->all())
                ->map(fn ($flow, $type) => ['value' => $type, 'label' => $flow->label()])
                ->values()
                ->all(),
            'inlineRunLimit' => self::INLINE_RUN_LIMIT,
            'n8nApiConfigured' => N8nApi::make()->isConfigured(),
            // Drives whether the per-run retry is offered at all.
            'queueBusy' => GencysSyncBatch::query()->active()->exists(),
            'query' => [
                'syncType' => $syncType,
                'status' => $status,
                'expanded' => array_values($expanded),
                'page' => $request->input('page'),
                'perPage' => $request->input('per_page'),
            ],
        ]);
    }

    /**
     * Ask n8n how the execution behind this run actually went.
     *
     * Answered as JSON for an in-page lookup rather than an Inertia reload: it's
     * a per-row question about a third-party system, and it can be slow or come
     * back with nothing.
     */
    public function execution(Request $request, Workspace $workspace, GencysSyncRun $run): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless((int) $run->workspace_id === (int) $workspace->id, 404);

        return response()->json(
            N8nApi::make()->lookup((string) $run->n8n_execution_id)
        );
    }

    /**
     * Run this subject again.
     *
     * n8n's public API has no retry — that is an editor action behind a browser
     * session — so this re-sends the work rather than poking the old execution,
     * which is the better answer anyway: a fresh batch re-reads the ERP
     * credentials and the delivered-PO list as they stand now instead of
     * replaying a stale payload.
     *
     * A new batch rather than reopening the old one: that batch may have closed
     * hours ago, and rewriting finished history to squeeze a run back in makes
     * the record harder to trust than an extra row does.
     */
    public function retry(Request $request, Workspace $workspace, GencysSyncRun $run, BatchRunner $runner): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless((int) $run->workspace_id === (int) $workspace->id, 404);

        if (! $this->flows->has($run->sync_type)) {
            return back()->with('error', "{$run->sync_type} is not run by the batch queue.");
        }

        if ($run->status !== GencysSyncRun::STATUS_FAILED) {
            return back()->with('warning', "Run #{$run->id} didn't fail — there is nothing to re-run.");
        }

        // One batch holds the ERP at a time, so a retry raised while the queue is
        // busy would just sit behind it. Refusing is more honest than silently
        // adding to a line the operator can't see the end of.
        if (GencysSyncBatch::query()->active()->exists()) {
            return back()->with('warning', 'A sync is already in progress — retry once the queue is clear.');
        }

        $flow = $this->flows->for($run->sync_type);

        $batch = $runner->queue(
            syncTypes: [$run->sync_type],
            parameters: [$run->sync_type => $flow->parametersForRun($run)],
            workspaceId: $workspace->id,
            source: GencysSyncBatch::SOURCE_MANUAL,
            createdByUserId: $request->user()->id,
        );

        if ($batch->total_runs === 0) {
            return back()->with('error', "Nothing to re-run for #{$run->id} — its item or workspace is no longer syncable.");
        }

        if (! $batch->wasRecentlyCreated) {
            return back()->with('warning', "That work is already queued as batch #{$batch->id}.");
        }

        return back()->with('success', "Queued batch #{$batch->id} to run {$flow->label()} again.");
    }

    /** This workspace's runs, narrowed by whatever filters are on. */
    private function scopeRuns(Builder $query, Request $request, Workspace $workspace, ?string $syncType, ?string $status): Builder
    {
        return $query
            ->where('workspace_id', $workspace->id)
            ->visibleTo($request->user(), $workspace)
            ->when($syncType, fn (Builder $q) => $q->where('sync_type', $syncType))
            ->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    /**
     * Status tallies per batch for the page's batches, in one query — this is
     * what the collapsed rows show without loading any runs.
     *
     * @return array<int, array<string, int>>
     */
    private function countsByBatch(Request $request, Workspace $workspace, array $batchIds, ?string $syncType, ?string $status): array
    {
        if (empty($batchIds)) {
            return [];
        }

        return GencysSyncRun::query()
            ->tap(fn (Builder $query) => $this->scopeRuns($query, $request, $workspace, $syncType, $status))
            ->whereIn('gencys_sync_batch_id', $batchIds)
            ->selectRaw('gencys_sync_batch_id, status, COUNT(*) as total')
            ->groupBy('gencys_sync_batch_id', 'status')
            ->get()
            ->groupBy('gencys_sync_batch_id')
            ->map(fn (Collection $rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all())
            ->all();
    }

    /**
     * The runs to render inline, keyed by batch. Only expanded batches are
     * queried, and each is capped — past that the batch's own page is the
     * better place to look.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function runsForExpanded(Request $request, Workspace $workspace, array $batchIds, ?string $syncType, ?string $status): array
    {
        if (empty($batchIds)) {
            return [];
        }

        $runs = [];

        foreach ($batchIds as $batchId) {
            $runs[$batchId] = GencysSyncRun::query()
                ->tap(fn (Builder $query) => $this->scopeRuns($query, $request, $workspace, $syncType, $status))
                ->where('gencys_sync_batch_id', $batchId)
                ->with('inventoryItem:id,product_id,sku')
                ->orderBy('id')
                ->limit(self::INLINE_RUN_LIMIT)
                ->get()
                ->map(fn (GencysSyncRun $run) => $this->presentRun($run))
                ->all();
        }

        return $runs;
    }

    /** @return array<int, int> */
    private function expandedIds(Request $request): array
    {
        return collect((array) $request->input('expanded', []))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function presentBatch(GencysSyncBatch $batch): array
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
            'parameters' => $batch->parameters,
            'total_runs' => $batch->total_runs,
            'succeeded_runs' => $batch->succeeded_runs,
            'failed_runs' => $batch->failed_runs,
            'cancelled_runs' => $batch->cancelled_runs,
            'progress' => $batch->progressPercent(),
            'queued_at' => $batch->queued_at,
            'started_at' => $batch->started_at,
            'finished_at' => $batch->finished_at,
        ];
    }

    /** @return array<string, mixed> */
    private function presentRun(GencysSyncRun $run): array
    {
        return [
            'id' => $run->id,
            'sync_type' => $run->sync_type,
            'sync_label' => $this->flows->has($run->sync_type)
                ? $this->flows->for($run->sync_type)->label()
                : $run->sync_type,
            'status' => $run->status,
            'attempt' => $run->attempt,
            'n8n_execution_id' => $run->n8n_execution_id,
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
