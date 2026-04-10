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
        $date = Carbon::yesterday()->format('Y-m-d');

        PancakeUser::query()
            ->whereHas('systemUser.workspaces.apiKeys')
            ->with('systemUser.workspaces.apiKeys')
            ->get()
            ->each(function ($pancakeUser) use ($date) {
               $pancakeUser->systemUser->workspaces->each(function ($workspace) use ($pancakeUser, $date) {
                    $data = [
                        'workspace_id' => $workspace->id,
                        'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
                        'csr_id' => $pancakeUser->id,
                        'csr_name' => $pancakeUser->name,
                        'date' => $date,
                        'webhook_url' => config('app.url') . '/api/v1/public/csr-daily-records',
                    ];

                    Http::post(config('services.n8n.webhook_url'), $data);

                    $this->info("Triggered for CSR {$pancakeUser->name} (ID: {$pancakeUser->id}) in workspace {$workspace->id}");
                });
            });
    }
}
