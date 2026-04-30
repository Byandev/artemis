<?php

namespace App\Console\Commands;

use App\Jobs\TriggerFetchCsrErpDailRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Models\User as PancakeUser;

class TriggerFetchCsrErpDailRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-csr-erp-dail-records {--delay=30} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger n8n webhook for each Pancake user to fetch ERP daily records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.webhook_url).');

            return 1;
        }

        $delay = (int) $this->option('delay');

        try {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))->format('m/d/Y')
                : Carbon::yesterday()->format('m/d/Y');
        } catch (\Exception) {
            $this->error("Invalid date provided: {$this->option('date')}");

            return 1;
        }

        $query = PancakeUser::query()
            ->where('status', 'ACTIVE')
            ->whereHas('systemUser.workspaces.apiKeys')
            ->with('systemUser.workspaces.apiKeys')
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No Pancake users found with linked workspaces and API keys.');

            return 0;
        }

        $this->info("Dispatching {$total} CSR(s) with {$delay}s delay between jobs for date: {$date}");

        $dispatched = 0;
        $skipped = 0;

        $query->lazy()->each(function ($pancakeUser) use ($date, $webhookUrl, $delay, &$dispatched, &$skipped) {
            foreach ($pancakeUser->systemUser->workspaces as $workspace) {
                $apiKey = $workspace->apiKeys->first();

                if (! $apiKey) {
                    $this->warn("Skipping workspace {$workspace->id} — no API key found.");
                    $skipped++;

                    continue;
                }

                $data = [
                    'workspace_id' => $workspace->id,
                    'workspace_api_key' => $apiKey->reveal(),
                    'csr_id' => $pancakeUser->id,
                    'csr_name' => $pancakeUser->name,
                    'date' => $date,
                    'webhook_url' => config('app.url').'/api/v1/public/csr-daily-records',
                ];

                $jobDelaySeconds = $dispatched * $delay;

                TriggerFetchCsrErpDailRecord::dispatch($webhookUrl, $data)
                    ->delay(now()->addSeconds($jobDelaySeconds));

                $this->info("Dispatched for CSR {$pancakeUser->name} (ID: {$pancakeUser->id}) in workspace {$workspace->id} (delay: {$jobDelaySeconds}s)");
                $dispatched++;
            }
        });

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped}");

        return 0;
    }
}
