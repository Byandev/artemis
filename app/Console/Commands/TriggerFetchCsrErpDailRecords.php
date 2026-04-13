<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\User as PancakeUser;

class TriggerFetchCsrErpDailRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-csr-erp-dail-records';

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

        $date = Carbon::yesterday()->format('Y-m-d');

        $users = PancakeUser::query()
            ->whereHas('systemUser.workspaces.apiKeys')
            ->with('systemUser.workspaces.apiKeys')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No Pancake users found with linked workspaces and API keys.');
            return 0;
        }

        $this->info("Processing {$users->count()} CSR(s) for date: {$date}");

        $succeeded = 0;
        $failed = 0;

        $users->each(function ($pancakeUser) use ($date, $webhookUrl, &$succeeded, &$failed) {
            $pancakeUser->systemUser->workspaces->each(function ($workspace) use ($pancakeUser, $date, $webhookUrl, &$succeeded, &$failed) {
                $apiKey = $workspace->apiKeys->first();

                if (! $apiKey) {
                    $this->warn("Skipping workspace {$workspace->id} — no API key found.");
                    $failed++;
                    return;
                }

                $data = [
                    'workspace_id' => $workspace->id,
                    'workspace_api_key' => $apiKey->reveal(),
                    'csr_id' => $pancakeUser->id,
                    'csr_name' => $pancakeUser->name,
                    'date' => $date,
                    'webhook_url' => config('app.url') . '/api/v1/public/csr-daily-records',
                ];

                try {
                    $response = Http::timeout(30)->post($webhookUrl, $data);

                    if ($response->successful()) {
                        $this->info("Triggered for CSR {$pancakeUser->name} (ID: {$pancakeUser->id}) in workspace {$workspace->id}");
                        $succeeded++;
                    } else {
                        $this->error("Failed for CSR {$pancakeUser->name} (ID: {$pancakeUser->id}) in workspace {$workspace->id} — HTTP {$response->status()}");
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error for CSR {$pancakeUser->name} (ID: {$pancakeUser->id}) in workspace {$workspace->id} — {$e->getMessage()}");
                    $failed++;
                }
            });
        });

        $this->newLine();
        $this->info("Done. Succeeded: {$succeeded}, Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
