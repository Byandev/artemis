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

    private const STAT_COLUMNS = ['delivery', 'is_clicked', 'seen', 'sent', 'total_phone_number'];

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

            $new = [];
            $old = [];
            foreach (self::STAT_COLUMNS as $col) {
                $new[$col] = (int) ($payload[$col] ?? 0);
                $old[$col] = (int) $flow->{$col};
            }

            // First-time stats fetch for this flow: just baseline the cumulative
            // values on the flow row — nothing to attribute to today, since we
            // don't know which day the historical totals accrued on.
            $hasBaseline = array_sum($old) > 0
                || FlowDailyStat::where('flow_id', $flow->id)->exists();

            if ($hasBaseline) {
                $delta = [];
                foreach (self::STAT_COLUMNS as $col) {
                    // Clamp negatives — Botcake counters can drop on flow reset/delete.
                    $delta[$col] = max(0, $new[$col] - $old[$col]);
                }

                // Add the delta to today's row so multiple same-day runs accumulate
                // correctly instead of overwriting earlier deltas.
                $daily = FlowDailyStat::firstOrNew([
                    'flow_id' => $flow->id,
                    'date' => now()->toDateString(),
                ]);

                foreach (self::STAT_COLUMNS as $col) {
                    $daily->{$col} = (int) ($daily->{$col} ?? 0) + $delta[$col];
                }

                $daily->save();
            }

            // Always advance the flow's cumulative to the latest snapshot so the
            // next run measures its delta against this baseline.
            $flow->update($new);
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
