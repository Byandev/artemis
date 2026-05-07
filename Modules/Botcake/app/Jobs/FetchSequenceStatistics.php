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

    private const STAT_COLUMNS = ['delivery', 'seen', 'sent', 'total_phone_number'];

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
                ->fetchSequenceStatistics($sequence->id);
        } catch (Throwable $e) {
            Log::warning('Botcake sequence statistics fetch failed', [
                'sequence_id' => $sequence->id,
                'page_id' => $sequence->page_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $payload = collect($data);
        $today = now()->toDateString();
        $now = now();

        // Snapshot the existing per-message cumulative BEFORE upserting the new
        // values, so we can compute deltas against the previous run. The
        // Botcake message id is now our PK, so we key by `id`.
        $existingById = SequenceMessage::query()
            ->where('sequence_id', $sequence->id)
            ->get()
            ->keyBy('id');

        $messagesWithDailyHistory = SequenceMessageDailyStat::query()
            ->whereIn('sequence_message_id', $existingById->keys())
            ->distinct()
            ->pluck('sequence_message_id')
            ->flip();

        // Compute per-message deltas against the prior cumulative. New messages
        // (no prior baseline) are skipped — we don't know which day their
        // historical counts accrued on.
        $deltasById = [];
        foreach ($payload as $item) {
            $messageId = $item['message_id'] ?? null;
            if ($messageId === null) {
                continue;
            }

            $existing = $existingById->get($messageId);

            $hasBaseline = $existing
                && (
                    ((int) $existing->delivery + (int) $existing->seen + (int) $existing->sent + (int) $existing->total_phone_number) > 0
                    || $messagesWithDailyHistory->has($existing->id)
                );

            if (! $hasBaseline) {
                continue;
            }

            $new = [
                'delivery' => (int) ($item['delivery'] ?? 0),
                'seen' => (int) ($item['seen'] ?? 0),
                'sent' => (int) ($item['sends'] ?? 0),
                'total_phone_number' => (int) ($item['total_phone_number'] ?? 0),
            ];

            $delta = [];
            foreach (self::STAT_COLUMNS as $col) {
                // Clamp negatives — Botcake counters can drop on reset/delete.
                $delta[$col] = max(0, $new[$col] - (int) $existing->{$col});
            }

            $deltasById[$messageId] = $delta;
        }

        // Upsert messages with the latest cumulative values so the next run has
        // a fresh baseline. `id` is the Botcake message id and is the PK.
        $items = collect($payload)
            ->filter(fn ($item) => isset($item['message_id']))
            ->map(function ($item) use ($sequence, $now) {
                return [
                    'id' => $item['message_id'],
                    'sequence_id' => $sequence->id,
                    'delivery' => (int) ($item['delivery'] ?? 0),
                    'name' => $item['name'] ?? $item['message_id'],
                    'seen' => (int) ($item['seen'] ?? 0),
                    'sent' => (int) ($item['sends'] ?? 0),
                    'total_phone_number' => (int) ($item['total_phone_number'] ?? 0),
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            })
            ->values()
            ->toArray();

        SequenceMessage::upsert($items, ['id']);

        // Add per-message deltas to today's row (firstOrNew + add so multiple
        // same-day runs accumulate correctly instead of overwriting).
        $sequenceDelta = array_fill_keys(self::STAT_COLUMNS, 0);

        foreach ($deltasById as $messageId => $delta) {
            // After the upsert, every message in $deltasById exists in the table
            // with $messageId as its id — so we can use $messageId directly.

            $daily = SequenceMessageDailyStat::firstOrNew([
                'sequence_message_id' => $messageId,
                'date' => $today,
            ]);

            foreach (self::STAT_COLUMNS as $col) {
                $daily->{$col} = (int) ($daily->{$col} ?? 0) + $delta[$col];
                $sequenceDelta[$col] += $delta[$col];
            }

            $daily->save();
        }

        // Roll the per-message deltas up into a sequence-level daily delta row.
        if (! empty($deltasById)) {
            $sequenceDaily = SequenceDailyStat::firstOrNew([
                'sequence_id' => $sequence->id,
                'date' => $today,
            ]);

            foreach (self::STAT_COLUMNS as $col) {
                $sequenceDaily->{$col} = (int) ($sequenceDaily->{$col} ?? 0) + $sequenceDelta[$col];
            }

            $sequenceDaily->save();
        }
    }
}
