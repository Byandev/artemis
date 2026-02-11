<?php

namespace Modules\Botcake\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\Botcake\Models\Sequence;
use Modules\Botcake\Models\SequenceMessage;

class FetchSequenceStatistics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Sequence $sequence) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sequence = $this->sequence->load('page');

        $response = Http::withHeaders([
            'access-token' => $sequence->page->botcake_token,
        ])
            ->get("https://botcake.io/api/public_api/v1/pages/{$sequence->page->id}/sequences/{$sequence->sequence_id}/statistics")
            ->throw()
            ->json();

        $items = collect($response['data'])->map(function ($item) use ($sequence) {
            return [
                'sequence_id' => $sequence->sequence_id,
                'message_id' => $item['message_id'],
                'delivery' => $item['delivery'],
                'name' => $item['name'] ?? $item['message_id'],
                'seen' => $item['seen'],
                'sent' => $item['sends'],
                'total_phone_number' => $item['total_phone_number'],
            ];
        })->toArray();

        SequenceMessage::upsert($items, ['message_id', 'sequence_id']);
    }
}
