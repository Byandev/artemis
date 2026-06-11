<?php

namespace Modules\MetaAds\Jobs;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

class EvaluateOptimizationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Run on the dedicated Meta Ads Horizon queue (max 3 processes). */
    public $queue = 'meta-ads';

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public readonly Workspace $workspace) {}

    public function handle(OptimizationRuleEvaluator $evaluator): void
    {
        // Only automatic rules apply unattended; approval-mode rules are surfaced
        // as proposals for a human to confirm. When two rules match the same
        // target, the higher priority claims it (ties fall back to the older rule).
        $rules = OptimizationRule::where('workspace_id', $this->workspace->id)
            ->where('is_active', true)
            ->where('execution_mode', 'automatic')
            ->with(['conditions', 'adAccounts'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // One id for the whole run — the apply jobs use it to claim each target
        // so no campaign / ad set is changed by more than one rule this run.
        $runId = (string) Str::uuid();

        // Evaluate everything first (DB reads only), deduped to one change per
        // campaign/ad set, then apply each in its own queued job.
        foreach ($evaluator->planRun($rules) as $decision) {
            ApplyOptimizationAction::dispatch(
                $runId,
                $decision['rule'],
                $decision['adAccount'],
                $decision['target'],
                $decision['snapshot'],
            );
        }
    }
}
