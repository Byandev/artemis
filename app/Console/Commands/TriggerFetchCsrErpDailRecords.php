<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = Carbon::yesterday()->format('m/d/Y');

        User::has('pancakeAccounts')
            ->with('pancakeAccounts')
            ->with('workspaces.apiKeys')
            ->has('workspaces.apiKeys')
            ->get()
            ->each(function ($user) use ($date) {
                $user->workspaces->each(function ($workspace) use ($user, $date) {
                    $data = [
                        'workspace_id' => $workspace->id,
                        'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
                        'csr_id' => $user->id,
                        'date' => $date,
                        'pancake_accounts' => $user->pancakeAccounts->map(fn ($account) => $account->name)->toArray(),
                    ];




                    dd($data);
                });
            });
    }
}
