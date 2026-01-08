<?php

namespace App\Jobs\AdsManager;

use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\FacebookAccount;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchCampaigns implements ShouldQueue
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
                'fields' => 'id,name,account_id,status,daily_budget,created_time,start_time,stop_time,updated_time,effective_status',
                'access_token' => $this->facebookAccount->access_token,
                'limit' => 1000,
            ];

            sleep(1);

            if ($after != '') {
                $params['after'] = $after;
            }

            $response = Http::get("https://graph.facebook.com/v22.0/act_{$this->adAccount->id}/campaigns", $params)
                ->throw();

            $data = $response->json();

            foreach ($data['data'] as $campaign) {
                Campaign::updateOrCreate([
                    'id' => $campaign['id'],
                ], [
                    'ad_account_id' => $campaign['account_id'],
                    'name' => $campaign['name'],
                    'status' => $campaign['status'],
                    'start_time' => isset($campaign['start_time']) ? Carbon::parse($campaign['start_time'])
                        ->setTimezone('Asia/Manila')
                        ->format('Y-m-d H:i:s') : null,
                    'end_time' => isset($campaign['end_time']) ? Carbon::parse($campaign['end_time'])
                        ->setTimezone('Asia/Manila')
                        ->format('Y-m-d H:i:s') : null,
                    'daily_budget' => isset($campaign['daily_budget'])
                        ? $campaign['daily_budget'] / 100 : 0,
                    'effective_status' => $campaign['effective_status'],
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
