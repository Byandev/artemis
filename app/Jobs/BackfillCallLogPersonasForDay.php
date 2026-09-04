<?php

namespace App\Jobs;

use App\Models\CallLog;
use App\Support\CallLogPersona;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-runs the persona match over one workspace's calls on one day.
 *
 * A day at a time because both rules query per workspace and date anyway, and
 * because a backfill reaching back months is otherwise one long transaction
 * holding a worker for the whole run. Split this way a failed day retries on
 * its own without redoing the rest, and the days spread across workers.
 *
 * Re-running a day is safe: only rows still missing something are selected, so
 * a second pass over a finished day is a read and nothing more.
 */
class BackfillCallLogPersonasForDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @param  string  $rule  all, delivery, or verification
     * @param  bool  $dryRun  Count what would be stamped and write nothing. Only
     *                        ever set when the command runs the job itself — a
     *                        queued dry run would have nowhere to report to.
     */
    public function __construct(
        public int $workspaceId,
        public string $date,
        public string $rule = 'all',
        public bool $dryRun = false,
    ) {}

    /**
     * @return array<string, int> scanned, delivery, verification, ambiguous, unmatched
     */
    public function handle(): array
    {
        $totals = ['scanned' => 0, 'delivery' => 0, 'verification' => 0, 'ambiguous' => 0, 'unmatched' => 0];

        $calls = self::pending(CallLog::query())
            ->where('workspace_id', $this->workspaceId)
            ->whereDate('call_date', $this->date)
            ->get(['id', 'phone_number', 'persona']);

        $totals['scanned'] = $calls->count();

        if ($calls->isEmpty()) {
            return $totals;
        }

        $phones = $calls->pluck('phone_number')->filter()->unique()->values()->all();

        $deliveryMatches = $this->rule === 'verification'
            ? []
            : CallLogPersona::resolve($this->workspaceId, $this->date, $phones);

        // Only numbers the delivery rule could not place are worth asking the
        // verification rule about, and a row already stamped customer or rider
        // is not up for reclassification — it is here for its missing
        // order_for_delivery_id alone.
        $leftover = $this->rule === 'delivery'
            ? []
            : $calls->filter(fn ($call) => $call->persona === null && ! isset($deliveryMatches[$call->phone_number]))
                ->pluck('phone_number')
                ->filter()
                ->unique()
                ->values()
                ->all();

        $verificationMatches = $leftover === []
            ? []
            : CallLogPersona::resolveVerification($this->workspaceId, $this->date, $leftover);

        // Grouped by the values being written so a day goes out in a handful of
        // updates rather than one per call.
        $updates = [];

        foreach ($calls as $call) {
            $match = $deliveryMatches[$call->phone_number]
                ?? ($call->persona === null ? ($verificationMatches[$call->phone_number] ?? null) : null);

            if ($match === null) {
                $totals['unmatched']++;

                continue;
            }

            if ($match['persona'] === CallLogPersona::VERIFICATION) {
                $totals['verification']++;

                if (! empty($match['ambiguous'])) {
                    $totals['ambiguous']++;
                }
            } else {
                $totals['delivery']++;
            }

            $key = $match['persona'].'|'.$match['order_id'].'|'.($match['order_for_delivery_id'] ?? '');
            $updates[$key]['values'] = [
                'persona' => $match['persona'],
                'order_id' => $match['order_id'],
                'order_for_delivery_id' => $match['order_for_delivery_id'],
            ];
            $updates[$key]['ids'][] = $call->id;
        }

        if ($this->dryRun || $updates === []) {
            return $totals;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                CallLog::whereIn('id', $update['ids'])->update([
                    ...$update['values'],
                    'updated_at' => now(),
                ]);
            }
        });

        // The command that dispatched this is long gone by the time it runs, so
        // the log is the only place the numbers show up for a queued backfill.
        Log::info('Backfilled call log personas', [
            'workspace_id' => $this->workspaceId,
            'date' => $this->date,
            'rule' => $this->rule,
            ...$totals,
        ]);

        return $totals;
    }

    /**
     * Calls with something still to stamp.
     *
     * Two kinds qualify: one that was never matched at all, and one matched to a
     * delivery back when order_for_delivery_id did not exist yet.
     */
    public static function pending($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('persona')
                ->orWhere(function ($sub) {
                    $sub->whereNull('order_for_delivery_id')
                        ->whereIn('persona', [CallLogPersona::CUSTOMER, CallLogPersona::RIDER]);
                });
        });
    }
}
