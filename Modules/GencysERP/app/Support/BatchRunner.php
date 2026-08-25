<?php

namespace Modules\GencysERP\Support;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Jobs\SendGencysSyncGroup;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;
use Throwable;

/**
 * Walks Gencys ERP sync batches, one group of runs at a time.
 *
 * The whole engine is a single re-entrant tick(): whoever has news — a trigger
 * command that just queued a batch, an n8n callback that just resolved a run, the
 * timeout sweeper — calls tick(), and it works out what should happen next. Only
 * one caller is ever inside it (a cache lock), and it only ever leaves one group
 * in flight, so a workspace never has two ERP sessions open at once.
 *
 * The rules it enforces:
 *  - one batch runs at a time, the oldest queued one goes next, nothing is ever
 *    cancelled to make room;
 *  - a group is sent only once the previous group has fully reported back or
 *    timed out;
 *  - a run that fails or times out is re-sent on its own, ahead of fresh work,
 *    until the batch's retries run out — then it's failed and the batch carries on.
 */
class BatchRunner
{
    private const LOCK = 'gencys-erp:batch-runner';

    /** Batches drained in one tick before we hand back — a runaway guard. */
    private const MAX_STEPS = 50;

    public function __construct(private readonly SyncFlowRegistry $flows) {}

    /**
     * Move the ERP sync queue forward as far as it can go right now.
     *
     * Safe to call from anywhere, as often as you like: if another caller holds
     * the lock they're already doing this work, and if a group is still in flight
     * this returns without touching anything.
     */
    public function tick(): void
    {
        $lock = Cache::lock(self::LOCK, 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->drain();
        } catch (Throwable $e) {
            Log::error('Gencys batch runner failed', ['error' => $e->getMessage()]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Raise a batch covering one or more sync types and set it going.
     *
     * $parameters is keyed by sync type — ['transaction_history' => ['dates' =>
     * [...]], ...] — so one batch can carry a different window for each.
     *
     * If an equivalent batch is already queued and unstarted, that one is handed
     * back untouched instead of a second copy being built. The schedule fires each
     * sync type several times a day and a batch that's waiting its turn is never
     * cancelled to make room, so without this a slow morning would leave three
     * identical batches stacked up, each re-fetching what the one before it did.
     */
    public function queue(
        array $syncTypes,
        array $parameters = [],
        ?int $workspaceId = null,
        string $source = GencysSyncBatch::SOURCE_CRON,
        ?int $createdByUserId = null,
    ): GencysSyncBatch {
        $syncTypes = array_values(array_unique($syncTypes));

        // Resolve every flow up front so an unknown type throws before anything
        // is written, rather than halfway through building the runs.
        foreach ($syncTypes as $syncType) {
            $this->flows->for($syncType);
        }

        $batch = GencysSyncBatch::open(
            syncTypes: $syncTypes,
            parameters: $parameters,
            workspaceId: $workspaceId,
            source: $source,
            createdByUserId: $createdByUserId,
        );

        if (! $batch->wasRecentlyCreated) {
            return $batch;
        }

        // Runs are built type by type in the order given, and the queue walks
        // them by id, so that order is also the order they'll be sent.
        $created = 0;

        foreach ($syncTypes as $syncType) {
            $created += $this->flows->for($syncType)->buildRuns($batch);
        }

        // Nothing to ask the ERP for — no ERP-connected workspace, no syncable
        // items. Drop the batch rather than leave an empty no-op row cluttering
        // the queue five times a day. The returned model still carries its
        // attributes so the caller can report on it.
        if ($created === 0) {
            $batch->delete();

            return $batch;
        }

        $batch->refreshCounts();

        $this->tick();

        return $batch->refresh();
    }

    /** Cancel a batch and release the ERP for whatever is queued behind it. */
    public function cancel(GencysSyncBatch $batch, string $reason): void
    {
        if ($batch->isFinished()) {
            return;
        }

        $batch->runs()
            ->whereIn('status', [GencysSyncRun::STATUS_QUEUED, GencysSyncRun::STATUS_PENDING])
            ->get()
            ->each
            ->cancel($reason);

        $batch->forceFill([
            'status' => GencysSyncBatch::STATUS_CANCELLED,
            'finished_at' => now(),
            'message' => $reason,
        ])->save();

        $batch->refreshCounts();

        $this->tick();
    }

    /**
     * Time out the runs whose callback never arrived, retrying them if the batch
     * still has attempts left for them. Returns [retried, failed].
     *
     * This is what stops a silent n8n failure from parking the whole queue: a
     * group that never reports back can only hold the ERP for as long as its
     * batch's timeout.
     *
     * @return array{0: int, 1: int}
     */
    public function expireTimedOutRuns(): array
    {
        $retried = 0;
        $failed = 0;

        GencysSyncRun::query()
            ->pending()
            ->whereNotNull('timeout_at')
            ->where('timeout_at', '<=', now())
            ->with('batch')
            ->get()
            ->each(function (GencysSyncRun $run) use (&$retried, &$failed) {
                $maxRetries = $run->batch?->max_retries ?? 0;
                $waited = $run->batch?->timeout_seconds ?? 0;

                if ($run->attempt < $maxRetries) {
                    $retried++;
                    $run->requeueForRetry(
                        "No callback within {$waited}s — retrying (attempt ".($run->attempt + 2).' of '.($maxRetries + 1).').'
                    );

                    return;
                }

                $failed++;
                $run->fail("No callback within {$waited}s after ".($run->attempt + 1).' attempt(s).');
            });

        return [$retried, $failed];
    }

    /**
     * Do as much as can be done in one pass: finish batches that are out of work,
     * promote the next queued batch, and send one group.
     */
    private function drain(): void
    {
        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $batch = $this->currentBatch();

            if (! $batch) {
                return;
            }

            // A group is still out with n8n — nothing to do until it reports back
            // or its timeout expires.
            if ($batch->runs()->pending()->exists()) {
                return;
            }

            $runs = $this->nextRuns($batch);

            if ($runs->isEmpty()) {
                $this->finish($batch);

                continue;
            }

            if ($this->send($batch, $runs)) {
                return;
            }

            // The group couldn't be sent at all and its runs are failed; keep
            // going so one misconfigured workspace doesn't stall the batch.
        }
    }

    /** The running batch, promoting the oldest queued one if the ERP is free. */
    private function currentBatch(): ?GencysSyncBatch
    {
        if ($batch = GencysSyncBatch::query()->running()->orderBy('id')->first()) {
            return $batch;
        }

        $batch = GencysSyncBatch::query()->queued()->orderBy('id')->first();

        if (! $batch) {
            return null;
        }

        $batch->forceFill([
            'status' => GencysSyncBatch::STATUS_RUNNING,
            'started_at' => now(),
        ])->save();

        return $batch;
    }

    /**
     * The next runs to send: a retry on its own if one is waiting, otherwise the
     * head of the queue plus its group-mates, up to the batch's group size.
     *
     * @return Collection<int, GencysSyncRun>
     */
    private function nextRuns(GencysSyncBatch $batch): Collection
    {
        // Retries jump the queue so a subject is re-attempted right after the
        // group it dropped out of, and always travel alone.
        $retry = $batch->runs()->queued()->where('attempt', '>', 0)->orderBy('id')->first();

        if ($retry) {
            return collect([$retry]);
        }

        $next = $batch->runs()->queued()->orderBy('id')->first();

        if (! $next) {
            return collect();
        }

        if (blank($next->group_key)) {
            return collect([$next]);
        }

        // Group size belongs to the sync type, not the batch: n8n takes an
        // items[] array for some flows and a single subject for others. A batch
        // may pin one size for everything, but normally each type uses its own.
        $size = $batch->group_size ?: $this->flows->for($next->sync_type)->defaultGroupSize();

        return $batch->runs()->queued()
            ->where('group_key', $next->group_key)
            ->where('attempt', 0)
            ->orderBy('id')
            ->limit(max(1, $size))
            ->get();
    }

    /**
     * Send one group to n8n. Returns false when the group couldn't go out at all
     * (its runs are failed in that case) so the caller can move on.
     *
     * @param  Collection<int, GencysSyncRun>  $runs
     */
    private function send(GencysSyncBatch $batch, Collection $runs): bool
    {
        // Every run in a group shares a group key, so they share a sync type too.
        $syncType = $runs->first()->sync_type;
        $flow = $this->flows->for($syncType);
        $parameters = $batch->parametersFor($syncType);

        $workspace = Workspace::with('apiKeys')->find($runs->first()->workspace_id);

        if (! $workspace || $workspace->apiKeys->isEmpty()) {
            $runs->each->fail('Workspace is no longer connected to the ERP (missing workspace or API key).');
            $batch->refreshCounts();

            return false;
        }

        $webhookUrl = $flow->webhookUrl($parameters);

        if (blank($webhookUrl)) {
            $runs->each->fail("No n8n webhook configured for {$syncType}.");
            $batch->refreshCounts();

            return false;
        }

        $payload = $flow->buildPayload($batch, $workspace, $runs);

        // Marked before the POST leaves: from here on the runs are in flight and
        // their timeout is the only thing that can bring them back.
        $runs->each->markSent($batch->timeout_seconds);

        $job = new SendGencysSyncGroup($webhookUrl, $payload, $runs->pluck('id')->all());

        // `inline` is set by a --sync command run, which exists so a developer can
        // fire at an n8n test-mode webhook without a queue worker.
        if (data_get($parameters, 'inline')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        return true;
    }

    /** Close a batch that has no work left. */
    private function finish(GencysSyncBatch $batch): void
    {
        $batch->refreshCounts();

        $batch->forceFill([
            'status' => $batch->failed_runs > 0
                ? GencysSyncBatch::STATUS_COMPLETED_WITH_FAILURES
                : GencysSyncBatch::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();

        Log::info('Gencys sync batch finished', [
            'batch_id' => $batch->id,
            'sync_types' => $batch->sync_types,
            'status' => $batch->status,
            'succeeded' => $batch->succeeded_runs,
            'failed' => $batch->failed_runs,
        ]);
    }
}
