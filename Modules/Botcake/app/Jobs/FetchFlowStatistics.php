<?php

namespace Modules\Botcake\Jobs;

use App\Services\Botcake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Botcake\Models\Flow;
use Modules\Botcake\Models\FlowDailyStat;
use Throwable;

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

        try {
            $payload = (new Botcake($flow->page->id, $flow->page->botcake_token))
                ->fetchFlowStatistics($flow->id);

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
        } catch (Throwable $e) {
            Log::warning('Botcake flow statistics fetch failed', [
                'flow_id' => $flow->id,
                'page_id' => $flow->page_id,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
