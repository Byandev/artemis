<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\ErpSyncRun;
use Modules\GencysERP\Services\ErpSyncService;

class TriggerFetchERPPurchaseOrders extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-purchase-orders
        {--start-date= : Start of the PO date range in Y-m-d format (defaults to 3 months ago)}
        {--end-date= : End of the PO date range in Y-m-d format (defaults to today)}
        {--delay=10 : Seconds to stagger each queued workspace by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP purchase orders';

    public function handle(ErpSyncService $sync)
    {
        $webhookUrl = $this->option('webhook') ?: config('services.n8n.purchase_order_webhook_url');

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

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $this->info("Queueing ERP purchase orders for {$startDate->format('m/d/Y')} – {$endDate->format('m/d/Y')}…");

        $totalItems = 0;
        $totalChunks = 0;

        foreach ($workspaces as $workspace) {
            $run = $sync->startPurchaseOrderRun(
                workspace: $workspace,
                startDate: $startDate,
                endDate: $endDate,
                trigger: ErpSyncRun::TRIGGER_SCHEDULE,
                webhookOverride: $this->option('webhook') ?: null,
            );

            if ($run) {
                $totalItems += $run->total_items;
                $totalChunks += $run->total_chunks;
            }
        }

        $this->newLine();
        $this->info("Queued {$totalChunks} chunk(s) covering {$totalItems} item(s).");

        return 0;
    }
}
