<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Purchase orders, one run per workspace over a single date range.
 *
 * Like transaction history, n8n reads the ERP's purchase-order list for the
 * whole range in one pass and posts back every order on it, so there is nothing
 * to fan out per item: the range is the subject and one run covers it.
 *
 * The delivered-PO exclusion list is still rebuilt at send time rather than when
 * the batch was raised, so a batch that waited its turn doesn't re-fetch POs
 * that closed while it was queued.
 *
 * Parameters (under the `purchase_order` key of the batch): start_date, end_date
 * (m/d/Y), without_delivered, webhook, inline.
 */
class PurchaseOrderFlow extends SyncFlow
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_PURCHASE_ORDER;
    }

    public function label(): string
    {
        return 'Purchase orders';
    }

    public function webhookUrl(array $parameters = []): ?string
    {
        return data_get($parameters, 'webhook')
            ?: config('services.n8n.purchase_order_webhook_url');
    }

    /** The last three months — POs stay open long enough to need the run-up. */
    public function defaultParameters(): array
    {
        return [
            'start_date' => Carbon::now()->subMonths(3)->startOfDay()->format('m/d/Y'),
            'end_date' => Carbon::today()->format('m/d/Y'),
        ];
    }

    public function parametersForRun(GencysSyncRun $run): array
    {
        return array_filter([
            'start_date' => data_get($run->meta, 'start_date'),
            'end_date' => data_get($run->meta, 'end_date'),
        ]);
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $parameters = $batch->parametersFor($this->type());

        $meta = [
            'start_date' => data_get($parameters, 'start_date'),
            'end_date' => data_get($parameters, 'end_date'),
        ];

        $created = 0;

        foreach ($this->eligibleWorkspaces($batch)->get() as $workspace) {
            $this->queueRun($batch, $workspace->id, $this->groupKey($workspace->id), $meta);
            $created++;
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $run = $runs->first();
        $parameters = $batch->parametersFor($this->type());

        $delivered = data_get($parameters, 'without_delivered')
            ? []
            : $workspace->closedPurchasedOrders()->pluck('cust_po_no')->all();

        return [
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'start_date' => data_get($parameters, 'start_date'),
            'end_date' => data_get($parameters, 'end_date'),
            'webhook_url' => $this->callbackUrl('api/v1/public/purchase-orders/bulk-sync'),
            // Only needed if the list is too big to post back in one call: the
            // data callback then heartbeats instead of closing the run, and this
            // is what ends it. See GencysSyncRun::finishById().
            'finish_webhook_url' => $this->callbackUrl('api/v1/public/gencys/sync-runs/finish'),
            'sync_run_id' => $run->id,
            'delivered_purchase_orders_no' => $delivered,
        ];
    }
}
