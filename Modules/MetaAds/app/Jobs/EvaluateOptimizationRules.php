<?php

namespace Modules\MetaAds\Jobs;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

class EvaluateOptimizationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public readonly Workspace $workspace) {}

    public function handle(OptimizationRuleEvaluator $evaluator): void
    {
        $rules = OptimizationRule::where('workspace_id', $this->workspace->id)
            ->where('is_active', true)
            ->with('conditions')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $adAccounts = AdAccount::forWorkspace($this->workspace)->get();

        if ($adAccounts->isEmpty()) {
            return;
        }

        foreach ($rules as $rule) {
            foreach ($adAccounts as $adAccount) {
                try {
                    $evaluator->evaluate($rule, $adAccount);
                } catch (\Throwable $e) {
                    Log::error('Failed to evaluate optimization rule', [
                        'rule_id'        => $rule->id,
                        'ad_account_id'  => $adAccount->id,
                        'workspace_id'   => $this->workspace->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
