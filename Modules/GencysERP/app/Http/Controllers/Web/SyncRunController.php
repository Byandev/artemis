<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\N8nApi;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;

/**
 * The per-run actions offered beside a batch's runs: ask n8n how a run's
 * execution went, and re-send a failed one.
 *
 * There is no listing here — runs are read on the batch pages. These are the two
 * things you can *do* to a single run, which is why they live apart from the
 * views that merely show it.
 */
class SyncRunController extends Controller
{
    public function __construct(private readonly SyncFlowRegistry $flows) {}

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
}
