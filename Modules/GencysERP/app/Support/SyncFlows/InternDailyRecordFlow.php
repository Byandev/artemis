<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Each intern's daily figures, one run per intern per date.
 *
 * n8n's webhook takes a single intern and a single date per call, so this is the
 * widest-fanning flow in the queue: a week of dates across a dozen interns is
 * eighty-odd runs. That is exactly why it belongs here rather than on the old
 * fixed timer — TriggerFetchInternDailyRecordsCommand fired every one of those
 * calls up front, staggered by a `--delay` guess, with nothing stopping the
 * next batch from opening a second ERP session on top of them. The queue sends
 * one at a time and waits for each to report back.
 *
 * The subject list is the synced roster: active interns carrying a Gencys
 * `intern_id`, the same filter the command used. A workspace whose roster has
 * never been pulled contributes no runs, so pairing this with Interns in one
 * batch is worth doing — the roster lands first and this reads it.
 *
 * Parameters (under the `intern_daily_records` key of the batch): dates[]
 * (m/d/Y), intern_ids[] to narrow the roster, webhook, inline.
 */
class InternDailyRecordFlow extends SyncFlow
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_INTERN_DAILY_RECORDS;
    }

    public function label(): string
    {
        return 'Intern daily records';
    }

    public function webhookUrl(array $parameters = []): ?string
    {
        return data_get($parameters, 'webhook')
            ?: config('services.n8n.gencys_intern_daily_records_webhook_url')
            ?: config('services.n8n.webhook_url');
    }

    /** Yesterday, the day the retired command defaulted to. */
    public function defaultParameters(): array
    {
        return ['dates' => [Carbon::yesterday()->format('m/d/Y')]];
    }

    /**
     * A retry re-asks for that one intern on that one date — not the whole
     * roster, and not the rest of the window.
     */
    public function parametersForRun(GencysSyncRun $run): array
    {
        return array_filter([
            'dates' => array_filter([data_get($run->meta, 'date')]),
            'intern_ids' => array_filter([data_get($run->meta, 'intern_id')]),
        ]);
    }

    /**
     * Scheduled only. One run per intern per day means a range picked by hand
     * would fan into hundreds of ERP calls, so this isn't offered in the form —
     * the daily pass asks for yesterday and that is enough.
     */
    public function offeredInBatchForm(): bool
    {
        return false;
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $parameters = $batch->parametersFor($this->type());
        $dates = (array) data_get($parameters, 'dates', []);
        $internIds = array_filter((array) data_get($parameters, 'intern_ids', []));

        $created = 0;

        foreach ($this->eligibleWorkspaces($batch)->get() as $workspace) {
            // Only interns the roster sync has actually seen: without a Gencys
            // intern_id there is nothing to ask the ERP about. Deduped because
            // the run key is the intern id, so a repeated one would raise two
            // runs asking the identical question.
            $interns = $workspace->interns()
                ->where('active', true)
                ->whereNotNull('intern_id')
                ->when($internIds !== [], fn ($query) => $query->whereIn('intern_id', $internIds))
                ->pluck('intern_id')
                ->unique()
                ->values();

            foreach ($dates as $date) {
                foreach ($interns as $internId) {
                    $this->queueRun(
                        $batch,
                        $workspace->id,
                        $this->groupKey($workspace->id, ["d:{$date}", "i:{$internId}"]),
                        ['date' => $date, 'intern_id' => $internId],
                    );
                    $created++;
                }
            }
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $run = $runs->first();

        return [
            'workspace_id' => $workspace->id,
            // `api_key`, matching the retired command byte for byte — the
            // deployed n8n workflow reads this name, and no finish webhook is
            // sent because the data callback closes the run itself (see
            // Api\InternDailyRecordController).
            'api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'webhook_url' => $this->callbackUrl('api/v1/public/gencys/intern-daily-records'),
            'intern_id' => data_get($run->meta, 'intern_id'),
            'sync_run_id' => $run->id,
            'date' => data_get($run->meta, 'date'),
        ];
    }
}
