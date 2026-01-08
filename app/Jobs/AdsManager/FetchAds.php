<?php

namespace App\Jobs\AdsManager;

use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\FacebookAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchAds implements ShouldQueue
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
                'fields' => 'id,name,account_id,status,campaign_id,effective_status,adset_id,effective_object_story_id,creative{id,name,effective_object_story_id,object_story_spec{page_id,instagram_actor_id}}',
                'access_token' => $this->facebookAccount->access_token,
                'limit' => 1000,
            ];

            if ($after != '') {
                $params['after'] = $after;
            }

            $response = Http::get("https://graph.facebook.com/v22.0/act_{$this->adAccount->id}/ads", $params)
                ->throw();

            $data = $response->json();

            foreach ($data['data'] as $ad) {
                Ad::updateOrCreate([
                    'id' => $ad['id'],
                ], [
                    'campaign_id' => $ad['campaign_id'],
                    'ad_set_id' => $ad['adset_id'],
                    'ad_account_id' => $ad['account_id'],
                    'name' => $ad['name'],
                    'status' => $ad['status'],
                    'effective_status' => $ad['effective_status'],
                    'page_id' => isset($ad['creative']) && isset($ad['creative']['effective_object_story_id']) ? explode('_', $ad['creative']['effective_object_story_id'])[0] : null,
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
