<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Inventory transaction history, one run per date.
 *
 * n8n reads the ERP's transaction report for a date in a single pass and posts
 * back everything it found there, grouped by item name — so a date, not an item,
 * is the subject of the sync. One run covers the whole date, which is also why
 * there is never more than one transaction-history run in flight per workspace.
 *
 * The item list used to travel with the request, a run per item and twenty per
 * call. It doesn't any more: we no longer tell the ERP which items to look up,
 * we take whatever the report contains — including items we have never seen,
 * which the callback creates rather than drops. See TransactionHistoryController.
 *
 * Parameters (under the `transaction_history` key of the batch): dates[] (m/d/Y),
 * webhook, inline.
 */
class TransactionHistoryFlow extends SyncFlow
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_TRANSACTION_HISTORY;
    }

    public function label(): string
    {
        return 'Transaction history';
    }

    public function webhookUrl(array $parameters = []): ?string
    {
        return data_get($parameters, 'webhook')
            ?: config('services.n8n.transaction_history_webhook_url');
    }

    /** Yesterday and today — enough to catch late-posted movements. */
    public function defaultParameters(): array
    {
        return [
            'dates' => [
                Carbon::yesterday()->format('m/d/Y'),
                Carbon::today()->format('m/d/Y'),
            ],
        ];
    }

    public function parametersForRun(GencysSyncRun $run): array
    {
        return array_filter([
            'dates' => array_filter([data_get($run->meta, 'date')]),
        ]);
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $dates = (array) data_get($batch->parametersFor($this->type()), 'dates', []);
        $workspaces = $this->eligibleWorkspaces($batch)->get();

        $created = 0;

        foreach ($dates as $date) {
            foreach ($workspaces as $workspace) {
                $this->queueRun(
                    $batch,
                    $workspace->id,
                    $this->groupKey($workspace->id, ["d:{$date}"]),
                    ['date' => $date],
                );
                $created++;
            }
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $run = $runs->first();

        return [
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'date' => data_get($run->meta, 'date'),
            'webhook_url' => $this->callbackUrl('api/v1/public/inventory-items/transactions/bulk-sync'),
            // Only needed if the report is too big to post back in one call: the
            // data callback then heartbeats instead of closing the run, and this
            // is what ends it. See GencysSyncRun::finishById().
            'finish_webhook_url' => $this->callbackUrl('api/v1/public/gencys/sync-runs/finish'),
            'sync_run_id' => $run->id,
        ];
    }
}
