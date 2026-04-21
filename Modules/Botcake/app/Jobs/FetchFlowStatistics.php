<?php

namespace Modules\Botcake\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Botcake\Models\Flow;
use Modules\Botcake\Models\FlowDailyStat;

class FetchFlowStatistics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

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

        if (! $response->ok()) {
            Log::warning('Botcake flow statistics fetch failed', [
                'flow_id' => $flow->id,
                'botcake_flow_id' => $flow->flow_id,
                'page_id' => $flow->page_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $payload = $response->json('data', []);

        $stats = [
            'delivery' => (int) ($payload['delivery'] ?? 0),
            'is_clicked' => (int) ($payload['is_clicked'] ?? 0),
            'seen' => (int) ($payload['seen'] ?? 0),
            'sent' => (int) ($payload['sent'] ?? 0),
            'total_phone_number' => (int) ($payload['total_phone_number'] ?? 0),
        ];

        $flow->update($stats);

        FlowDailyStat::upsert([
            [
                'flow_id' => $flow->id,
                'date' => now()->toDateString(),
                ...$stats,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['flow_id', 'date'], ['delivery', 'is_clicked', 'seen', 'sent', 'total_phone_number', 'updated_at']);
    }
}
