<?php

namespace App\Jobs;

use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\FacebookAccount;
use Carbon\Carbon;
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
            $params = [
                'fields' => 'id,name,campaign_id,status,daily_budget,created_time,updated_time',
                'access_token' => $this->facebookAccount->access_token,
                'limit' => 1000,
            ];

            sleep(1);

            if ($after != '') {
                $params['after'] = $after;
            }

            $response = Http::get("https://graph.facebook.com/v22.0/act_{$this->adAccount->id}/adsets", $params)
                ->throw();

            $data = $response->json();

            foreach ($data['data'] as $adSet) {
                AdSet::updateOrCreate([
                    'id' => $adSet['id'],
                ], [
                    'ad_account_id' => $adSet['campaign_id'] ? $this->adAccount->id : null,
                    'campaign_id' => $adSet['campaign_id'] ?? null,
                    'name' => $adSet['name'],
                    'status' => $adSet['status'],
                    'daily_budget' => $adSet['daily_budget'] ?? null,
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
