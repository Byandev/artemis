<?php

namespace Modules\Botcake\Jobs;

use App\Services\Botcake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Botcake\Models\Sequence;
use Modules\Botcake\Models\SequenceDailyStat;
use Modules\Botcake\Models\SequenceMessage;
use Modules\Botcake\Models\SequenceMessageDailyStat;
use Throwable;

class FetchSequenceStatistics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

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

        try {
            $data = (new Botcake($sequence->page->id, $sequence->page->botcake_token))
                ->fetchSequenceStatistics($sequence->sequence_id);
        } catch (Throwable $e) {
            Log::warning('Botcake sequence statistics fetch failed', [
                'sequence_id' => $sequence->id,
                'botcake_sequence_id' => $sequence->sequence_id,
                'page_id' => $sequence->page_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $payload = collect($data);
        $today = now()->toDateString();
        $now = now();

        $items = $payload->map(function ($item) use ($sequence, $now) {
            return [
                'sequence_id' => $sequence->id,
                'message_id' => $item['message_id'] ?? null,
                'delivery' => (int) ($item['delivery'] ?? 0),
                'name' => $item['name'] ?? $item['message_id'],
                'seen' => (int) ($item['seen'] ?? 0),
                'sent' => (int) ($item['sends'] ?? 0),
                'total_phone_number' => (int) ($item['total_phone_number'] ?? 0),
                'updated_at' => $now,
                'created_at' => $now,
            ];
        })->toArray();

        SequenceMessage::upsert($items, ['message_id', 'sequence_id']);

        $messages = SequenceMessage::query()
            ->where('sequence_id', $sequence->id)
            ->get(['id', 'delivery', 'seen', 'sent', 'total_phone_number']);

        if ($messages->isNotEmpty()) {
            $dailyMessageRows = $messages->map(function (SequenceMessage $message) use ($today, $now) {
                return [
                    'sequence_message_id' => $message->id,
                    'date' => $today,
                    'delivery' => (int) $message->delivery,
                    'seen' => (int) $message->seen,
                    'sent' => (int) $message->sent,
                    'total_phone_number' => (int) $message->total_phone_number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            SequenceMessageDailyStat::upsert(
                $dailyMessageRows,
                ['sequence_message_id', 'date'],
                ['delivery', 'seen', 'sent', 'total_phone_number', 'updated_at']
            );
        }

        SequenceDailyStat::upsert([
            [
                'sequence_id' => $sequence->id,
                'date' => $today,
                'delivery' => (int) $messages->sum('delivery'),
                'seen' => (int) $messages->sum('seen'),
                'sent' => (int) $messages->sum('sent'),
                'total_phone_number' => (int) $messages->sum('total_phone_number'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['sequence_id', 'date'], ['delivery', 'seen', 'sent', 'total_phone_number', 'updated_at']);
    }
}
