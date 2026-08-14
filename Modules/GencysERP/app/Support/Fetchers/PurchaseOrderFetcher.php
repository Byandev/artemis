<?php

namespace Modules\GencysERP\Support\Fetchers;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Jobs\FetchInventoryItemPurchaseOrders;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Purchase orders: a single date range (not iterated day by day like
 * transaction history), items chunked, and a list of already-delivered PO
 * numbers so n8n can skip re-scraping orders that are closed.
 */
class PurchaseOrderFetcher extends ChunkedItemFetcher
{
    /** How far back the PO range reaches when no dates are given. */
    private const DEFAULT_MONTHS_BACK = 3;

    public function type(): string
    {
        return GencysSyncRun::TYPE_PURCHASE_ORDER;
    }

    protected function webhookConfigKey(): string
    {
        return 'services.n8n.purchase_order_webhook_url';
    }

    protected function callbackPath(): string
    {
        return '/api/v1/public/purchase-orders/bulk-sync';
    }

    public function loadWorkspaces(Builder $query): Collection
    {
        return $query->with([
            'apiKeys',
            'closedPurchasedOrders' => fn ($q) => $q->select(['cust_po_no', 'workspace_id']),
            'inventoryItems' => $this->itemConstraint(),
        ])->get();
    }

    /** Wider than transaction history — PO pages are heavier. */
    protected function chunkDelayMinutes(): int
    {
        return 3;
    }

    protected function makeJob(string $webhookUrl, array $data, array $syncRunIds): object
    {
        return new FetchInventoryItemPurchaseOrders($webhookUrl, $data, $syncRunIds);
    }

    protected function describe(array $passes): string
    {
        return "purchase orders for {$passes[0]['start_date']} – {$passes[0]['end_date']}";
    }

    /** A single pass: the whole range goes to n8n in one call per chunk. */
    protected function passes(): array
    {
        [$start, $end] = $this->resolveRange();

        return [[
            'start_date' => $start->format('m/d/Y'),
            'end_date' => $end->format('m/d/Y'),
        ]];
    }

    protected function payloadExtras(Workspace $workspace): array
    {
        return [
            // Sending an empty list makes n8n re-fetch every PO instead of
            // skipping the ones already delivered.
            'delivered_purchase_orders_no' => $this->option('without-delivered')
                ? []
                : $workspace->closedPurchasedOrders->map(fn ($po) => $po->cust_po_no)->toArray(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws \InvalidArgumentException
     */
    private function resolveRange(): array
    {
        $start = ($startOption = $this->option('start-date'))
            ? $this->parseDate($startOption, 'start-date')
            : Carbon::now()->subMonths(self::DEFAULT_MONTHS_BACK)->startOfDay();

        $end = ($endOption = $this->option('end-date'))
            ? $this->parseDate($endOption, 'end-date')
            : Carbon::today();

        if ($start->gt($end)) {
            throw new \InvalidArgumentException(
                "Start date ({$start->toDateString()}) must not be after end date ({$end->toDateString()})."
            );
        }

        return [$start, $end];
    }
}
