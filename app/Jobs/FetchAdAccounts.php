<?php

namespace App\Jobs;

use App\Jobs\AdsManager\FetchAds;
use App\Jobs\AdsManager\FetchAdSets;
use App\Models\AdAccount;
use App\Models\FacebookAccount;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchAdAccounts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public FacebookAccount $facebookAccount)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = Http::get('https://graph.facebook.com/v22.0/me/adaccounts', [
            'fields' => 'id,name,account_id,currency,account_status,business_country_code',
            'access_token' => $this->facebookAccount->access_token,
        ]);

        $adAccounts = $response->json();

        foreach ($adAccounts['data'] as $adAccount) {
            $adAccount = AdAccount::updateOrCreate([
                'id' => $adAccount['account_id'],
            ], [
                'name' => $adAccount['name'],
                'currency' => $adAccount['currency'],
                'status' => $adAccount['account_status'],
                'country_code' => $adAccount['business_country_code']
                    ?? null,
            ]);

            $adAccount->facebook_accounts()->sync($this->facebookAccount->id);

            dispatch(new FetchCampaigns($this->facebookAccount, $adAccount));
            dispatch(new FetchAdSets($this->facebookAccount, $adAccount));
            dispatch(new FetchAds($this->facebookAccount, $adAccount));

            $start = Carbon::now()->subMonths(2);
            $end = Carbon::now();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                dispatch(new FetchAdRecords($this->facebookAccount, $adAccount, $date->toDateString()));
            }
        }
    }
}
