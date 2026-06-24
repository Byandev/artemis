<?php

namespace Modules\GencysERP\Console\Commands;

use Illuminate\Console\Command;
use Modules\GencysERP\Jobs\FetchUnitCodeInventoryJob;
use Modules\GencysERP\Models\GencysUnitCode;

class TriggerFetchUnitCodeInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gencys-erp:trigger-fetch-unit-code-inventory
        {--delay=30}
        {--limit= : Max number of unit codes to dispatch (for testing)}
        {--unit-code= : Only dispatch for this unit code id (for testing)}
        {--sync : POST to n8n immediately instead of queueing on the erp worker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger the n8n webhook for each unit code to fetch its Gencys ERP inventory items';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webhookUrl = config('services.n8n.gencys_unit_code_inventory_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.gencys_unit_code_inventory_webhook_url).');

            return self::FAILURE;
        }

        $delay = (int) $this->option('delay');
        $sync = (bool) $this->option('sync');

        // Unit codes whose workspace is wired for ERP automation (ERP credentials
        // + an API key for the n8n callback to authenticate with).
        $unitCodes = GencysUnitCode::query()
            ->whereHas('workspace', fn ($q) => $q->whereNotNull('erp_username')
                ->whereNotNull('erp_password')
                ->whereHas('apiKeys'))
            ->with('workspace.apiKeys')
            ->when($this->option('unit-code'), fn ($q) => $q->where('id', (int) $this->option('unit-code')))
            ->when($this->option('limit'), fn ($q) => $q->limit((int) $this->option('limit')))
            ->orderBy('id')
            ->get();

        if ($unitCodes->isEmpty()) {
            $this->warn('No unit codes found for workspaces with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        // URL n8n posts the fetched inventory items back to. Configurable so it can
        // point at a reachable host (local n8n, staging, tunnel) instead of being
        // hardcoded. Defaults to APP_URL when N8N_GENCYS_UNIT_CODE_INVENTORY_CALLBACK_URL isn't set.
        $callbackBase = rtrim(config('services.n8n.gencys_unit_code_inventory_callback_url') ?: config('app.url'), '/');
        $callbackUrl = "{$callbackBase}/api/v1/public/gencys/unit-code-inventories";

        $this->info($sync
            ? "Sending {$unitCodes->count()} unit code(s) synchronously"
            : "Dispatching {$unitCodes->count()} unit code(s) with {$delay}s delay between jobs");

        $dispatched = 0;
        $skipped = 0;

        foreach ($unitCodes as $unitCode) {
            $apiKey = $unitCode->workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping unit code {$unitCode->id} — no API key found for workspace {$unitCode->workspace_id}.");
                $skipped++;

                continue;
            }

            $data = [
                'workspace_id' => $unitCode->workspace_id,
                'workspace_api_key' => $apiKey->reveal(),
                // Gencys' own unit code id (row_id). n8n echoes it back on the
                // callback to map items to this unit code (matched on row_id).
                'row_id' => $unitCode->row_id,
                'unit_code' => $unitCode->unit_code,
                // ERP login the n8n pipeline authenticates with (password decrypted).
                'erp_username' => $unitCode->workspace->erp_username,
                'erp_password' => $unitCode->workspace->erp_password,
                'webhook_url' => $callbackUrl,
            ];

            if ($sync) {
                // Throttle: space sync calls by --delay seconds so n8n's browser
                // automation isn't hit with hundreds of requests at once (429).
                if ($dispatched > 0 && $delay > 0) {
                    sleep($delay);
                }

                // Run inline so the webhook fires immediately — no erp worker needed.
                FetchUnitCodeInventoryJob::dispatchSync($webhookUrl, $data);
                $this->info("Sent for unit code {$unitCode->id}");
            } else {
                $jobDelaySeconds = $dispatched * $delay;

                FetchUnitCodeInventoryJob::dispatch($webhookUrl, $data)
                    ->delay(now()->addSeconds($jobDelaySeconds));

                $this->info("Dispatched for unit code {$unitCode->id} (delay: {$jobDelaySeconds}s)");
            }

            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
