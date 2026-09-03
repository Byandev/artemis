<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * One kind of Gencys ERP sync, expressed as the two things a batch needs from it:
 * which runs make up the work, and how to turn a group of those runs into the
 * payload its n8n webhook expects.
 *
 * The payloads are deliberately unchanged from the fire-and-forget commands these
 * flows replaced — the n8n workflows on the other side didn't move. What changed
 * is only when they're sent: the batch holds each group until the last one has
 * come back. See BatchRunner.
 */
abstract class SyncFlow
{
    abstract public function type(): string;

    /** Human label for the UI and command output. */
    abstract public function label(): string;

    /** The n8n webhook this flow posts to, honouring a per-batch override. */
    abstract public function webhookUrl(array $parameters = []): ?string;

    /**
     * Create this batch's runs, all `queued`. Returns how many were created.
     */
    abstract public function buildRuns(GencysSyncBatch $batch): int;

    /**
     * The window this flow syncs when nobody says otherwise.
     *
     * Lives here rather than in the commands so the scheduled `gencys-erp:sync`
     * and a hand-run trigger command can't drift into asking the ERP for
     * different things.
     */
    abstract public function defaultParameters(): array;

    /**
     * The batch parameters that would re-run exactly one run and nothing else.
     *
     * Used to retry a single run: rather than reopening a batch that has already
     * finished, a fresh batch is raised for just that subject. It also means the
     * retry re-reads everything that might have moved on — ERP credentials, the
     * delivered-PO list — instead of replaying a stale payload.
     */
    abstract public function parametersForRun(GencysSyncRun $run): array;

    /**
     * The n8n request body for one group of runs. Every run in the group shares a
     * group key, so they're always one workspace and one set of parameters.
     *
     * @param  Collection<int, GencysSyncRun>  $runs
     */
    abstract public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array;

    /**
     * How many runs may travel in one n8n request.
     *
     * Every flow now sends one subject — a date, a range — per call and gets the
     * whole report back, so nothing overrides this. The batch's grouping is kept
     * because it costs nothing and a future flow taking a list may want it; a
     * batch can still pin its own size. See BatchRunner::nextRuns().
     */
    public function defaultGroupSize(): int
    {
        return 1;
    }

    /**
     * Whether this flow is asked for a date window.
     *
     * Every flow but the intern roster syncs a slice of time, so the batch form
     * offers one date range for the lot and each type reads it the way its n8n
     * workflow expects. A flow answering false is handed no window at all, and
     * the form says so rather than implying the dates did something.
     */
    public function usesWindow(): bool
    {
        return true;
    }

    /**
     * Whether the Sync Batches form offers this type as a tickable option.
     *
     * A flow answering false is still fully owned by the queue — scheduled,
     * retried and reported on like any other — it just isn't something to raise
     * by hand. That is for the ones whose fan-out makes a hand-raised batch a
     * poor idea: intern daily records opens a run per intern per day, so a week
     * picked in the form would be hundreds of ERP calls.
     */
    public function offeredInBatchForm(): bool
    {
        return true;
    }

    /** Where n8n posts its results back to. */
    protected function callbackUrl(string $path): string
    {
        $base = rtrim(config('services.n8n.callback_base_url') ?: config('app.url'), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Workspaces wired for ERP automation: credentials configured plus an API key
     * for the callback to authenticate with. A batch raised from one workspace's
     * UI is pinned to that workspace; a cron batch spans them all.
     */
    protected function eligibleWorkspaces(GencysSyncBatch $batch): Builder
    {
        return Workspace::query()
            ->whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->when($batch->workspace_id, fn (Builder $query) => $query->whereKey($batch->workspace_id))
            ->orderBy('id');
    }

    /** A group key runs are bundled by: same flow, same workspace, same parameters. */
    protected function groupKey(int $workspaceId, array $parts = []): string
    {
        return implode('|', array_merge([$this->type(), "ws:{$workspaceId}"], $parts));
    }

    /** Open one queued run belonging to this batch. */
    protected function queueRun(
        GencysSyncBatch $batch,
        int $workspaceId,
        string $groupKey,
        array $meta = [],
        ?int $inventoryItemId = null,
    ): GencysSyncRun {
        return GencysSyncRun::queue($batch, $workspaceId, $inventoryItemId, $this->type(), $groupKey, $meta);
    }
}
