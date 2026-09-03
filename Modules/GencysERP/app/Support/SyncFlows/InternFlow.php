<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * The intern roster, one run per workspace.
 *
 * Alone among the flows this one has no window. The ERP's intern list is a
 * roster rather than a report of a day, so there is nothing to slice by date:
 * one run asks for the whole list and the callback upserts it, keyed on Gencys'
 * own intern id. That is why usesWindow() is false — the batch form offers one
 * date range for everything it covers, and this flow would quietly ignore it.
 *
 * It came into the queue late. Until now the roster was scraped by a button on
 * the Interns page firing at n8n directly, which could open a second ERP session
 * behind a running batch's back — a workspace has one ERP login, and that is the
 * whole reason the queue is serial.
 *
 * Parameters (under the `interns` key of the batch): webhook, inline. Both are
 * developer overrides for pointing at a test-mode workflow; the flow needs
 * nothing at all to run.
 */
class InternFlow extends SyncFlow
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_INTERNS;
    }

    public function label(): string
    {
        return 'Interns';
    }

    public function webhookUrl(array $parameters = []): ?string
    {
        return data_get($parameters, 'webhook')
            ?: config('services.n8n.gencys_interns_webhook_url')
            ?: config('services.n8n.webhook_url');
    }

    /** Nothing to ask for — the roster is the whole subject. */
    public function defaultParameters(): array
    {
        return [];
    }

    /** Re-reading the roster is all this flow ever does, so a retry says nothing. */
    public function parametersForRun(GencysSyncRun $run): array
    {
        return [];
    }

    public function usesWindow(): bool
    {
        return false;
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $created = 0;

        foreach ($this->eligibleWorkspaces($batch)->get() as $workspace) {
            $this->queueRun($batch, $workspace->id, $this->groupKey($workspace->id));
            $created++;
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $run = $runs->first();

        return [
            'workspace_id' => $workspace->id,
            // `api_key`, not the `workspace_api_key` the other flows send: the
            // deployed interns workflow reads this name and posts it straight
            // back as `data.api_key`, which is how its callback authenticates.
            // See Api\InternController — that endpoint sits outside the api.key
            // middleware for exactly this reason.
            'api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'webhook_url' => $this->callbackUrl('api/v1/public/gencys/interns'),
            // Only needed if the roster ever outgrows a single post: the data
            // callback would then heartbeat instead of closing the run, and this
            // is what ends it. See GencysSyncRun::finishById().
            'finish_webhook_url' => $this->callbackUrl('api/v1/public/gencys/sync-runs/finish'),
            'sync_run_id' => $run->id,
        ];
    }
}
