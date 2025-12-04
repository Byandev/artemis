<?php

namespace App\Jobs;

use App\Models\AdAccount;
use App\Models\AdRecord;
use App\Models\FacebookAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchAdRecords implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public FacebookAccount $facebookAccount, public AdAccount $adAccount, public string $date) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cont = true;
        $after = '';

        while ($cont) {
            $params = [
                'fields' => "id,adset_id,campaign_id,account_id,insights.time_range({'since':'".$this->date."','until':'".$this->date."'}).fields(spend,impressions,reach,clicks,action_values)",
                'access_token' => $this->facebookAccount->access_token,
                'limit' => 1000,
            ];

            sleep(1);

            if ($after != '') {
                $params['after'] = $after;
            }

            $response = Http::get("https://graph.facebook.com/v22.0/act_{$this->adAccount->id}/ads", $params)
                ->throw();

            $data = $response->json();

            foreach ($data['data'] as $record) {
                if (isset($record['insights']['data'])) {
                    if (count($record['insights']['data']) > 0) {
                        $item = $record['insights']['data'][0];

                        if (isset($item['impressions'])) {
                            AdRecord::updateOrCreate([
                                'ad_id' => $record['id'],
                                'date' => $this->date,
                            ], [
                                'spend' => $item['spend'],
                                'impressions' => $item['impressions'],
                                'clicks' => $item['clicks'],
                                'reach' => $item['reach'],
                                'sales' => isset($item['action_values']) && count($item['action_values']) ? $item['action_values'][0]['value'] : 0,
                                'ad_account_id' => $record['account_id'],
                                'campaign_id' => $record['campaign_id'],
                                'ad_set_id' => $record['adset_id'],
                            ]);
                        }
                    }
                }
            }

            if (isset($data['paging']['next'])) {
                $after = $data['paging']['cursors']['after'];
            } else {
                $cont = false;
            }
        }
    }
}
