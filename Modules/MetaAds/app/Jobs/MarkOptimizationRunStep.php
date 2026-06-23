<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MetaAds\Models\OptimizationRun;

/**
 * Lightweight marker interleaved into the evaluate chain: when it runs, the
 * previous phase's jobs have finished and the next phase is about to start, so
 * it advances every run sharing this chain onto $step. One marker drives many
 * runs because several rules can share a single deduplicated sync pass. Kept
 * separate from the (shared) sync jobs so those stay free of run-tracking.
 */
class MarkOptimizationRunStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $optimizationRunIds  every run advanced by this chain
     */
    public function __construct(
        public array $optimizationRunIds,
        public string $step,
    ) {}

    public function handle(): void
    {
        OptimizationRun::whereIn('id', $this->optimizationRunIds)
            ->get()
            ->each
            ->markStep($this->step);
    }
}
