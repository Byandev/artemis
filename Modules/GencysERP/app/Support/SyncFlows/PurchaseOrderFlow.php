<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;

/**
 * Purchase orders, one run per item over a single date range.
 *
 * Like transaction history, n8n loops an items[] array on one ERP session, so a
 * group carries many runs. The delivered-PO exclusion list is rebuilt at send
 * time rather than when the batch was raised, so a batch that waited its turn
 * doesn't re-fetch POs that closed while it was queued.
 *
 * Parameters (under the `purchase_order` key of the batch): start_date, end_date
 * (m/d/Y), item_ids[], without_delivered, webhook, inline.
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

    public function defaultGroupSize(): int
    {
        return (int) config('gencyserp.batch.group_size', 20);
    }

    /** The last three months — POs stay open long enough to need the run-up. */
    public function defaultParameters(): array
    {
        return [
            'start_date' => Carbon::now()->subMonths(3)->startOfDay()->format('m/d/Y'),
            'end_date' => Carbon::today()->format('m/d/Y'),
        ];
    }

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $parameters = $batch->parametersFor($this->type());

        $itemIds = (array) data_get($parameters, 'item_ids', []);

        $meta = [
            'start_date' => data_get($parameters, 'start_date'),
            'end_date' => data_get($parameters, 'end_date'),
        ];

        $workspaces = $this->eligibleWorkspaces($batch)
            ->with(['inventoryItems' => fn ($query) => $this->syncableItems($query, $itemIds)])
            ->get();

        $created = 0;

        foreach ($workspaces as $workspace) {
            $groupKey = $this->groupKey($workspace->id);

            foreach ($workspace->inventoryItems as $item) {
                $this->queueRun($batch, $workspace->id, $groupKey, $meta, $item->id);
                $created++;
            }
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $skus = InventoryItem::query()
            ->whereIn('id', $runs->pluck('inventory_item_id')->filter())
            ->pluck('sku', 'id');

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
            'items' => $runs->map(fn (GencysSyncRun $run) => [
                'id' => $run->inventory_item_id,
                'keyword' => $skus[$run->inventory_item_id] ?? null,
                'sync_run_id' => $run->id,
            ])->values()->all(),
            'delivered_purchase_orders_no' => $delivered,
        ];
    }

    /** Same rule as transaction history — see TransactionHistoryFlow. */
    protected function syncableItems($query, array $itemIds)
    {
        $query->where('is_parent', false);

        return empty($itemIds)
            ? $query->where('is_active', true)
            : $query->whereIn('id', $itemIds);
    }
}
