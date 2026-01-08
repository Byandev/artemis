<?php

namespace App\Console\Commands;

use App\Jobs\AdsManager\FetchAds;
use App\Jobs\AdsManager\FetchAdSets;
use App\Jobs\AdsManager\FetchCampaigns;
use App\Models\AdAccount;
use App\Models\FacebookAccount;
use Illuminate\Console\Command;

class TriggerFetchAdsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-ads-data';

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
        FacebookAccount::with('adAccounts')
            ->get()
            ->each(function (FacebookAccount $facebookAccount) {
                $facebookAccount->adAccounts()->each(function (AdAccount $adAccount) use ($facebookAccount) {
                    dispatch(new FetchCampaigns($facebookAccount, $adAccount));
                    dispatch(new FetchAdSets($facebookAccount, $adAccount));
                    dispatch(new FetchAds($facebookAccount, $adAccount));
                });
            });
    }
}
