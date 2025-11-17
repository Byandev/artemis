<?php

namespace App\Jobs;

use App\Models\AdAccount;
use App\Models\FacebookAccount;
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
                'facebook_account_id' => $this->facebookAccount->id,
                'name' => $adAccount['name'],
                'currency' => $adAccount['currency'],
                'status' => $adAccount['account_status'],
                'country_code' => $adAccount['business_country_code']
                    ?? null,
            ]);

            //            dispatch(new FetchCampaigns($adAccount));
            //            dispatch(new FetchAdSets($adAccount));
            //            dispatch(new FetchAds($adAccount));
            //
            //            for ($i = 0; $i <= 29; $i++) {
            //                $date = \Illuminate\Support\Carbon::now()->subMonth()->startOfMonth()->addDays($i)->toDateString();
            //
            //                dispatch(new FetchAdRecords($adAccount, $date));
            //            }
        }
    }
}
