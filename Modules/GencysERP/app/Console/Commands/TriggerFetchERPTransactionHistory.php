<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\ErpSyncRun;
use Modules\GencysERP\Services\ErpSyncService;

class TriggerFetchERPTransactionHistory extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-erp-transaction-history
        {--date= : The transaction history date in Y-m-d format (defaults to yesterday)}
        {--delay=10 : Seconds to stagger each queued workspace by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use this to hit an n8n test-mode webhook)}';

    protected $description = 'Trigger n8n webhook for each workspace with ERP credentials to fetch its ERP transaction history';

    public function handle(ErpSyncService $sync)
    {
        $webhookUrl = $this->option('webhook') ?: config('services.n8n.transaction_history_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n transaction history webhook URL is not configured (services.n8n.transaction_history_webhook_url). Pass --webhook= to override.');

            return 1;
        }

        $dateOption = $this->option('date');

        try {
            $date = $dateOption
                ? Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay()
                : Carbon::yesterday();
        } catch (\Exception $e) {
            $this->error("Invalid date '{$dateOption}'. Expected format: Y-m-d (e.g. 2026-06-24).");

            return 1;
        }

        $delay = max(0, (int) $this->option('delay'));

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return 0;
        }

        $this->info("Queueing ERP transaction history for {$date->format('m/d/Y')}…");

        $totalItems = 0;
        $totalChunks = 0;

        foreach ($workspaces as $workspace) {
            $run = $sync->startTransactionHistoryRun(
                workspace: $workspace,
                date: $date,
                trigger: ErpSyncRun::TRIGGER_SCHEDULE,
                webhookOverride: $this->option('webhook') ?: null,
                delaySeconds: $delay,
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
