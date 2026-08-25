<?php

namespace Modules\GencysERP\Support\SyncFlows;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;

/**
 * Inventory transaction history, one run per item per date.
 *
 * n8n takes an items[] array and loops it on a single ERP session, so a group can
 * carry many runs — which is what keeps this from opening one ERP login per item
 * and tripping the rate limit.
 *
 * Parameters (under the `transaction_history` key of the batch): dates[] (m/d/Y),
 * item_ids[], webhook, inline.
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

    public function defaultGroupSize(): int
    {
        return (int) config('gencyserp.batch.group_size', 20);
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

    public function buildRuns(GencysSyncBatch $batch): int
    {
        $parameters = $batch->parametersFor($this->type());

        $dates = (array) data_get($parameters, 'dates', []);
        $itemIds = (array) data_get($parameters, 'item_ids', []);

        $workspaces = $this->eligibleWorkspaces($batch)
            ->with(['inventoryItems' => fn ($query) => $this->syncableItems($query, $itemIds)])
            ->get();

        $created = 0;

        foreach ($dates as $date) {
            foreach ($workspaces as $workspace) {
                $groupKey = $this->groupKey($workspace->id, ["d:{$date}"]);

                foreach ($workspace->inventoryItems as $item) {
                    $this->queueRun($batch, $workspace->id, $groupKey, ['date' => $date], $item->id);
                    $created++;
                }
            }
        }

        return $created;
    }

    public function buildPayload(GencysSyncBatch $batch, Workspace $workspace, Collection $runs): array
    {
        $skus = InventoryItem::query()
            ->whereIn('id', $runs->pluck('inventory_item_id')->filter())
            ->pluck('sku', 'id');

        return [
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'date' => data_get($runs->first()->meta, 'date'),
            'webhook_url' => $this->callbackUrl('api/v1/public/inventory-items/transactions/bulk-sync'),
            'items' => $runs->map(fn (GencysSyncRun $run) => [
                'id' => $run->inventory_item_id,
                'keyword' => $skus[$run->inventory_item_id] ?? null,
                'sync_run_id' => $run->id,
            ])->values()->all(),
        ];
    }

    /**
     * The items worth asking the ERP about: parent items are grouping
     * placeholders with no SKU, and an explicit selection beats the active-only
     * default so a single inactive item can still be re-synced.
     */
    protected function syncableItems($query, array $itemIds)
    {
        $query->where('is_parent', false);

        return empty($itemIds)
            ? $query->where('is_active', true)
            : $query->whereIn('id', $itemIds);
    }
}
