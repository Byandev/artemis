<?php

namespace Modules\Botcake\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\Botcake\Models\Flow;

class FetchFlowStatistics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Flow $flow) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $flow = $this->flow->load('page');

        $response = Http::withHeaders([
            'access-token' => $flow->page->botcake_token,
        ])
            ->get("https://botcake.io/api/public_api/v1/pages/{$flow->page->id}/flows/{$flow->flow_id}/statistics");

//        if ($response->getStatusCode() !== )
        if ($response->ok()) {
            $response = $response->json();

            $flow->update([
                'delivery' => $response['data']['delivery'],
                'is_clicked' => $response['data']['is_clicked'],
                'seen' => $response['data']['seen'],
                'sent' => $response['data']['sent'],
                'total_phone_number' => $response['data']['total_phone_number'],
            ]);
        }


    }
}
