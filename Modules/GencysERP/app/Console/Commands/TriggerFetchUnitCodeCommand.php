<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\GencysERP\Jobs\FetchUnitCodeJob;

class TriggerFetchUnitCodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gencys-erp:trigger-fetch-unit-code {--delay=30} {--sync : POST to n8n immediately instead of queueing on the erp worker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger the n8n webhook for each workspace to fetch its Gencys ERP unit codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webhookUrl = config('services.n8n.inventory_unit_code_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.inventory_unit_code_webhook_url).');

            return self::FAILURE;
        }

        $delay = (int) $this->option('delay');

        // Only workspaces wired for ERP automation: ERP credentials configured
        // and at least one API key for the n8n callback to authenticate with.
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
            ? "Sending {$workspaces->count()} workspace(s) synchronously"
            : "Dispatching {$workspaces->count()} workspace(s) with {$delay}s delay between jobs");

        // URL n8n posts the fetched unit codes back to. Configurable so it can
        // point at a reachable host (local n8n, staging, tunnel) instead of being
        // hardcoded. Defaults to APP_URL when N8N_GENCYS_UNIT_CODE_CALLBACK_URL isn't set.
        $callbackBase = rtrim(config('services.n8n.gencys_unit_code_callback_url') ?: config('app.url'), '/');
        $callbackUrl = "{$callbackBase}/api/v1/public/inventory/unit-codes/bulk-sync";
        $dispatched = 0;
        $skipped = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping workspace {$workspace->id} — no API key found.");
                $skipped++;

                continue;
            }

            // The API key alone identifies the workspace on the callback, so no
            // workspace id/slug is sent.
            $data = [
                'workspace_api_key' => $apiKey->reveal(),
                // ERP login the n8n pipeline authenticates with (password decrypted).
                'erp_username' => $workspace->erp_username,
                'erp_password' => $workspace->erp_password,
                'webhook_url' => $callbackUrl,
            ];

            if ($sync) {
                // Run inline so the webhook fires immediately — no erp worker needed.
                FetchUnitCodeJob::dispatchSync($webhookUrl, $data);
                $this->info("Sent for workspace {$workspace->name} (ID: {$workspace->id})");
            } else {
                $jobDelaySeconds = $dispatched * $delay;

                FetchUnitCodeJob::dispatch($webhookUrl, $data)
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
