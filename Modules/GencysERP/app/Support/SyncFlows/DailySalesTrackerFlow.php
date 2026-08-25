<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * The daily sales tracker, one run per workspace per date.
 *
 * n8n's webhook takes a single date per call, so these go out one run at a time —
 * which is what the old command did anyway, just on a fixed timer instead of
 * waiting for each one to land.
 *
 * Parameters (under the `daily_sales_tracker` key of the batch): dates[] (m/d/Y),
 * webhook, inline.
 */
class DailySalesTrackerFlow extends SyncFlow
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_DAILY_SALES_TRACKER;
    }

    public function label(): string
    {
        return 'Daily sales tracker';
    }

    public function webhookUrl(array $parameters = []): ?string
    {
        return data_get($parameters, 'webhook')
            ?: config('services.n8n.gencys_daily_sales_webhook_url')
            ?: config('services.n8n.webhook_url');
    }

    /** The last three days, ending yesterday — orders keep settling for a while. */
    public function defaultParameters(): array
    {
        return [
            'dates' => collect(range(3, 1))
                ->map(fn (int $daysAgo) => Carbon::today()->subDays($daysAgo)->format('m/d/Y'))
                ->all(),
        ];
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $dates = (array) data_get($batch->parametersFor($this->type()), 'dates', []);
        $workspaces = $this->eligibleWorkspaces($batch)->get();

        $created = 0;

        foreach ($workspaces as $workspace) {
            foreach ($dates as $date) {
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
            'workspace_slug' => $workspace->slug,
            'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'date' => data_get($run->meta, 'date'),
            'webhook_url' => $this->callbackUrl('api/v1/public/gencys/daily-sales-tracker'),
            'sync_run_id' => $run->id,
        ];
    }
}
