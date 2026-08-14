<?php

namespace Modules\GencysERP\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Services\N8nApi;

/**
 * Retries Gencys ERP sync runs that failed or timed out, by two routes.
 *
 * Preferred: replay the original n8n execution in place via the n8n API. The
 * replayed execution re-posts the payload it was given, so it carries the same
 * sync_run_id and closes out the very same run — and because the retry can load
 * the current workflow version, "fix the flow, hit retry" actually works.
 *
 * Fallback, for runs with no execution id (the webhook handshake failed, or n8n
 * never reported back at all, so there's no execution to replay): re-dispatch
 * through the same trigger commands the scheduler uses. Rebuilding the n8n
 * payload here instead would give us a second copy to keep in sync, and a retry
 * that sends something subtly different is worse than no retry at all.
 *
 * The fallback opens *new* runs rather than reopening the old ones, so the
 * failed attempt stays in the audit trail. The batch converges back to
 * `completed` on its own: when a retried run succeeds, GencysSyncRun's
 * resolveEarlierRunsWithSameParameters() flips the earlier failure for the same
 * parameters to success, since the data it was waiting on has now arrived.
 */
class RetryGencysSyncRuns
{
    private const FETCH_COMMAND = 'gencys-erp:trigger-fetch-data';

    /** Types the fetch command can replay. Intern records and page details can't. */
    private const REPLAYABLE_TYPES = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
    ];

    public function __construct(private readonly N8nApi $n8n) {}

    /** Retry every unresolved run in a batch. Returns how many were replayed. */
    public function forBatch(GencysSyncBatch $batch): int
    {
        $runs = $batch->runs()
            ->whereIn('status', [GencysSyncRun::STATUS_FAILED, GencysSyncRun::STATUS_PENDING])
            ->get();

        return $this->retry($runs, $batch);
    }

    /** Re-dispatch a single run, rolling it up under its own batch. */
    public function forRun(GencysSyncRun $run): int
    {
        // Runs opened before batching (or by the standalone per-type commands)
        // have no batch. Give the retry one so it still shows up on the timeline.
        $batch = $run->batch ?? GencysSyncBatch::start(
            $run->workspace_id,
            [$run->sync_type],
            GencysSyncBatch::TRIGGER_RETRY,
            ['retry_of_run_id' => $run->id],
        );

        return $this->retry(collect([$run]), $batch);
    }

    /**
     * @param  Collection<int, GencysSyncRun>  $runs
     */
    private function retry(Collection $runs, GencysSyncBatch $batch): int
    {
        // Runs that know which execution produced them can be replayed in n8n;
        // the rest have nothing to replay and need a fresh scrape.
        [$replayable, $unknown] = $runs->partition(fn (GencysSyncRun $run) => $run->n8n_execution_id !== null);

        [$replayed, $rejected] = $this->replayExecutions($replayable);

        // Whatever n8n wouldn't replay (API unconfigured, execution pruned,
        // instance unreachable) still deserves a retry — just the slow way.
        $count = $replayed + $this->redispatch($unknown->merge($rejected), $batch);

        $batch->refreshCounters();

        return $count;
    }

    /**
     * Ask n8n to replay each distinct execution behind these runs.
     *
     * @param  Collection<int, GencysSyncRun>  $runs
     * @return array{0: int, 1: Collection<int, GencysSyncRun>} replayed count, and the runs n8n refused
     */
    private function replayExecutions(Collection $runs): array
    {
        $replayed = 0;
        $rejected = collect();

        // One execution covers a whole 20-item chunk, so many runs share an id.
        // Retrying per run would hammer the same execution twenty times.
        $runs->groupBy('n8n_execution_id')->each(function (Collection $group, $executionId) use (&$replayed, $rejected) {
            if (! $this->n8n->retryExecution((int) $executionId)) {
                $rejected->push(...$group->all());

                return;
            }

            // The replayed execution re-posts the original payload, so these
            // exact runs will be closed out again by its callback.
            $group->each->reopen("Replaying n8n execution #{$executionId}.");

            $replayed += $group->count();
        });

        return [$replayed, $rejected];
    }

    /**
     * @param  Collection<int, GencysSyncRun>  $runs
     */
    private function redispatch(Collection $runs, GencysSyncBatch $batch): int
    {
        $dispatched = 0;

        // Runs asking the ERP for the same thing collapse into one command call,
        // so a batch with 40 failed items replays as two chunked webhooks rather
        // than 40 separate logins — the same rate-limit pressure the 20-item
        // chunking exists to avoid.
        $runs->groupBy(fn (GencysSyncRun $run) => $run->sync_type.'|'.GencysSyncRun::parameterSignature($run->meta))
            ->each(function (Collection $group) use ($batch, &$dispatched) {
                $first = $group->first();

                // Intern daily records and page details aren't part of the batched
                // sweep, so there's nothing here to replay them through.
                if (! in_array($first->sync_type, self::REPLAYABLE_TYPES, true)) {
                    return;
                }

                $options = [
                    '--type' => [$first->sync_type],
                    '--workspace' => [$batch->workspace_id],
                    '--batch' => $batch->id,
                    '--force' => true,
                ];

                $itemIds = $group->pluck('inventory_item_id')->filter()->unique()->values()->all();

                if ($itemIds !== []) {
                    $options['--item'] = $itemIds;
                }

                Artisan::call(self::FETCH_COMMAND, $options + $this->dateOptions($first));

                $dispatched += $group->count();
            });

        return $dispatched;
    }

    /** Rebuild the date options a run was originally dispatched with. */
    private function dateOptions(GencysSyncRun $run): array
    {
        $meta = $run->meta ?? [];

        return match ($run->sync_type) {
            GencysSyncRun::TYPE_TRANSACTION_HISTORY,
            GencysSyncRun::TYPE_DAILY_SALES_TRACKER => array_filter([
                '--date' => $this->toIsoDate($meta['date'] ?? null),
            ]),
            GencysSyncRun::TYPE_PURCHASE_ORDER => array_filter([
                '--start-date' => $this->toIsoDate($meta['start_date'] ?? null),
                '--end-date' => $this->toIsoDate($meta['end_date'] ?? null),
            ]),
            default => [],
        };
    }

    /**
     * Run meta records dates the way the ERP shows them (m/d/Y); the trigger
     * commands parse Y-m-d. Without this the retry silently re-fetches the
     * wrong day.
     */
    private function toIsoDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
