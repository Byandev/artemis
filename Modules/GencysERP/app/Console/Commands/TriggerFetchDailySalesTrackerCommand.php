<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\GencysERP\Jobs\FetchDailySalesTrackerJob;

class TriggerFetchDailySalesTrackerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gencys-erp:trigger-fetch-daily-sales-tracker {--delay=30} {--date=} {--sync : POST to n8n immediately instead of queueing on the erp worker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger the n8n webhook for each workspace to fetch its Gencys ERP daily sales tracker';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webhookUrl = config('services.n8n.gencys_daily_sales_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.gencys_daily_sales_webhook_url).');

            return self::FAILURE;
        }

        $delay = (int) $this->option('delay');

        try {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))->format('m/d/Y')
                : Carbon::yesterday()->format('m/d/Y');
        } catch (\Exception) {
            $this->error("Invalid date provided: {$this->option('date')}");

            return self::FAILURE;
        }

        // Only workspaces wired for ERP automation: ERP credentials configured
        // and at least one API key for the n8 n callback to authenticate with.
        $workspaces = $this->eligibleWorkspaces()
            ->with('apiKeys')
            ->orderBy('id')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');

        $this->info($sync
            ? "Sending {$workspaces->count()} workspace(s) synchronously for date: {$date}"
            : "Dispatching {$workspaces->count()} workspace(s) with {$delay}s delay between jobs for date: {$date}");

        // URL n8n posts the fetched daily-sales rows back to. Configurable so it can
        // point at a reachable host (local n8n, staging, tunnel) instead of being
        // hardcoded. Defaults to APP_URL when N8N_GENCYS_DAILY_SALES_CALLBACK_URL isn't set.
        $callbackBase = rtrim(config('services.n8n.gencys_daily_sales_callback_url') ?: config('app.url'), '/');
        $callbackUrl = "{$callbackBase}/api/v1/public/gencys/daily-sales-tracker";

        $dispatched = 0;
        $skipped = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping workspace {$workspace->id} — no API key found.");
                $skipped++;

                continue;
            }

            $data = [
                'workspace_id' => $workspace->id,
                'workspace_slug' => $workspace->slug,
                'workspace_api_key' => $apiKey->reveal(),
                // ERP login the n8n pipeline authenticates with (password decrypted).
                'erp_username' => $workspace->erp_username,
                'erp_password' => $workspace->erp_password,
                'date' => $date,
                'webhook_url' => $callbackUrl,
            ];

            if ($sync) {
                // Run inline so the webhook fires immediately — no erp worker needed.
                FetchDailySalesTrackerJob::dispatchSync($webhookUrl, $data);
                $this->info("Sent for workspace {$workspace->name} (ID: {$workspace->id})");
            } else {
                $jobDelaySeconds = $dispatched * $delay;

                FetchDailySalesTrackerJob::dispatch($webhookUrl, $data)
                    ->delay(now()->addSeconds($jobDelaySeconds));

                $this->info("Dispatched for workspace {$workspace->name} (ID: {$workspace->id}) (delay: {$jobDelaySeconds}s)");
            }

            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /** Workspaces wired for ERP automation. Kept for readability/testability. */
    private function eligibleWorkspaces(): Builder
    {
        return Workspace::query()
            ->whereNotNull('erp_username')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys');
    }
}
