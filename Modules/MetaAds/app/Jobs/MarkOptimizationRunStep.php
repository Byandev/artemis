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
 * it simply advances the run onto $step. Kept separate from the (shared) sync
 * jobs so those stay free of run-tracking concerns.
 */
class MarkOptimizationRunStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $optimizationRunId,
        public string $step,
    ) {}

    public function handle(): void
    {
        OptimizationRun::find($this->optimizationRunId)?->markStep($this->step);
    }
}
