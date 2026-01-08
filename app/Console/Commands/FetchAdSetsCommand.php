<?php

namespace App\Console\Commands;

use App\Jobs\AdsManager\FetchAdSets;
use App\Models\FacebookAccount;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class FetchAdSetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:fetch-ad-sets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch ad sets from Facebook for all ad accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching ad sets from Facebook...');

        $facebookAccounts = FacebookAccount::with('adAccounts')->get();
        $jobsDispatched = 0;

        foreach ($facebookAccounts as $facebookAccount) {
            foreach ($facebookAccount->adAccounts as $adAccount) {
                FetchAdSets::dispatch($facebookAccount, $adAccount);
                $jobsDispatched++;
            }
        }

        $this->info("Dispatched {$jobsDispatched} jobs to fetch ad sets.");
        $this->info('Jobs are processing in the queue. Check Laravel Horizon for progress.');

        return CommandAlias::SUCCESS;
    }
}
