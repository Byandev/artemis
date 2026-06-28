<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Jobs\FetchInventoryItemPurchaseOrders;

class TriggerFetchERPPurchaseOrders extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-purchase-orders
        {--start-date= : Start of the PO date range in Y-m-d format (defaults to 3 months ago)}
        {--end-date= : End of the PO date range in Y-m-d format (defaults to today)}
        {--delay=10 : Seconds to stagger each queued workspace by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP purchase orders';

    public function handle()
    {
        $webhookUrl = $this->option('webhook') ?: config('services.n8n.purchase_order_webhook_url');

        $this->info($webhookUrl);
        
        if (empty($webhookUrl)) {
            $this->error('n8n purchase order webhook URL is not configured (services.n8n.purchase_order_webhook_url). Pass --webhook= to override.');

            return 1;
        }

        try {
            $startDate = $this->option('start-date')
                ? Carbon::createFromFormat('Y-m-d', $this->option('start-date'))->startOfDay()
                : Carbon::now()->subMonths(3)->startOfDay();

            $endDate = $this->option('end-date')
                ? Carbon::createFromFormat('Y-m-d', $this->option('end-date'))->startOfDay()
                : Carbon::today();
        } catch (\Exception $e) {
            $this->error('Invalid date. Expected format: Y-m-d (e.g. 2026-06-24).');

            return 1;
        }

        if ($startDate->greaterThan($endDate)) {
            $this->error("Start date ({$startDate->format('Y-m-d')}) cannot be after end date ({$endDate->format('Y-m-d')}).");

            return 1;
        }

        $startDateFormatted = $startDate->format('m/d/Y');
        $endDateFormatted = $endDate->format('m/d/Y');

        $sync = (bool) $this->option('sync');
        $delay = max(0, (int) $this->option('delay'));

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with(['apiKeys', 'inventoryItems' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $this->info(($sync ? 'Sending' : 'Queueing')." ERP purchase orders for {$startDateFormatted} – {$endDateFormatted}…");

        $dispatched = 0;
        $totalCount = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            // One payload per workspace carrying every item, so n8n logs into the ERP
            // once and loops the items reusing that session. This is what avoids the
            // per-item logins that were tripping the ERP's rate limit (429).
            $workspace->inventoryItems
                ->chunk(10)
                ->values()
                ->each(function ($chunk) use (&$dispatched, &$totalCount, $apiKey, $workspace, $webhookUrl, $delay, $startDateFormatted, $endDateFormatted) {
                    $dispatched++;
                    $totalCount += count($chunk);

                    $callbackBase = rtrim(config('app.url'), '/');

                    $data = [
                        'workspace_id' => $workspace->id,
                        'workspace_api_key' => $apiKey->reveal(),
                        'erp_username' => $workspace->erp_username,
                        'erp_password' => $workspace->erp_password,
                        'start_date' => $startDateFormatted,
                        'end_date' => $endDateFormatted,
                        'webhook_url' => "{$callbackBase}/api/v1/public/purchase-orders/bulk-sync",
                        'items' => $chunk->map(fn ($item) => [
                            'id' => $item->id,
                            'keyword' => $item->sku,
                        ])->values()->toArray(),
                    ];

                    $offset = $dispatched * $delay;

                    dispatch(new FetchInventoryItemPurchaseOrders($webhookUrl, $data))
                        ->delay(now()->addMinutes($offset));
                });
        }

        $this->newLine();
        $this->info(($sync ? 'Sent' : 'Queued')." {$totalCount} workspace(s).");

        return 0;
    }
}
