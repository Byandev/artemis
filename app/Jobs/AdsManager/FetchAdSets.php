<?php

namespace App\Jobs\AdsManager;

use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\FacebookAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchAdSets implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public FacebookAccount $facebookAccount, public AdAccount $adAccount)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cont = true;
        $after = '';

        while ($cont) {
            sleep(1);
            $params = [
                'fields' => 'id,name,account_id,status,campaign_id,daily_budget,effective_status',
                'access_token' => $this->facebookAccount->access_token,
                'limit' => 1000,
            ];

            if ($after != '') {
                $params['after'] = $after;
            }

            $response
                = Http::get("https://graph.facebook.com/v22.0/act_{$this->adAccount->id}/adsets",
                    $params)
                    ->throw();

            $data = $response->json();

            foreach ($data['data'] as $adSet) {
                AdSet::updateOrCreate([
                    'id' => $adSet['id'],
                ], [
                    'campaign_id' => $adSet['campaign_id'],
                    'ad_account_id' => $adSet['account_id'],
                    'name' => $adSet['name'],
                    'effective_status' => $adSet['effective_status'],
                    'status' => $adSet['status'],
                    'daily_budget' => isset($adSet['daily_budget'])
                        ? $adSet['daily_budget'] / 100 : 0,
                ]);
            }

            if (isset($data['paging']['next'])) {
                $after = $data['paging']['cursors']['after'];
            } else {
                $cont = false;
            }
        }
    }
}
