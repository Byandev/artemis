<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRun;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;
use Throwable;

/**
 * Final link in the evaluate-optimization-rules chain: once today's ad accounts,
 * campaigns, ad sets, ads and insights have synced, evaluate the rules the
 * command selected as due — on fresh data. Automatic rules dispatch one apply
 * job per target; approval-mode rules refresh their pending proposals.
 *
 * Mirrors the single-owner / priority claiming the command used to do inline,
 * but with no console output (it now runs on the queue after the sync).
 */
class EvaluateDueOptimizationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $ruleIds  The rules the command selected as due this run.
     * @param  int|null  $optimizationRunId  The tracked run this evaluation belongs to, if any.
     */
    public function __construct(
        public array $ruleIds,
        public ?int $optimizationRunId = null,
    ) {}

    public function handle(OptimizationRuleEvaluator $evaluator): void
    {
        if ($this->ruleIds === []) {
            $this->completeRun();

            return;
        }

        // Reload (still-active) rules in the same priority order the command
        // selected them: when two rules contest a target the higher priority
        // claims it, ties fall back to the older rule for a deterministic result.
        $rules = OptimizationRule::query()
            ->whereIn('id', $this->ruleIds)
            ->where('is_active', true)
            ->with(['adAccounts', 'conditions'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $this->completeRun();

            return;
        }

        [$automatic, $approval] = $rules->partition(
            fn (OptimizationRule $rule) => $rule->execution_mode === 'automatic',
        );

        // One id for the whole run — the apply jobs use it to claim each target
        // so no campaign / ad set is changed by more than one rule this run.
        $runId = (string) Str::uuid();

        $dispatched = $this->applyAutomatic($evaluator, $automatic, $runId);
        $proposed = $this->proposeForApproval($evaluator, $approval);

        Log::info('Meta Ads optimization rules evaluated after sync', [
            'rule_ids' => $rules->pluck('id')->all(),
            'automatic_changes_dispatched' => $dispatched,
            'proposals_created' => $proposed,
        ]);

        // Evaluation is the final phase — the whole run is now done.
        $this->completeRun();
    }

    /**
     * Mark the tracked run finished (the evaluation is the last phase). No-op
     * when this evaluation isn't part of a tracked run.
     */
    private function completeRun(): void
    {
        if ($this->optimizationRunId !== null) {
            OptimizationRun::find($this->optimizationRunId)?->markCompleted();
        }
    }

    /**
     * If the evaluation itself errors out, surface it on the run so the panel
     * shows the failure rather than a run stuck "running" forever.
     */
    public function failed(Throwable $e): void
    {
        if ($this->optimizationRunId !== null) {
            OptimizationRun::find($this->optimizationRunId)?->markFailed($e->getMessage());
        }
    }

    /**
     * Automatic rules apply unattended: one queued job per affected campaign /
     * ad set, deduped so each entity is changed at most once this run.
     *
     * @param  Collection<int, OptimizationRule>  $rules
     * @return int number of apply jobs dispatched
     */
    private function applyAutomatic(OptimizationRuleEvaluator $evaluator, Collection $rules, string $runId): int
    {
        $rules = $rules->filter(fn (OptimizationRule $rule) => $rule->adAccounts->isNotEmpty());

        if ($rules->isEmpty()) {
            return 0;
        }

        $decisions = $evaluator->planRun($rules);

        foreach ($decisions as $decision) {
            ApplyOptimizationAction::dispatch(
                $runId,
                $decision['rule'],
                $decision['adAccount'],
                $decision['target'],
                $decision['snapshot'],
            )->onQueue('meta-ads');
        }

        return count($decisions);
    }

    /**
     * Approval rules never apply automatically — they refresh their pending
     * proposals for a human to review. Decided proposals (approved / rejected /
     * applied) are kept as history.
     *
     * @param  Collection<int, OptimizationRule>  $rules
     * @return int number of proposals created
     */
    private function proposeForApproval(OptimizationRuleEvaluator $evaluator, Collection $rules): int
    {
        $created = 0;

        // Each campaign / ad set is owned by the highest-priority rule that
        // matches it this run ($rules is pre-sorted by priority desc), so a
        // target never gets competing proposals — the same single-owner
        // behaviour automatic rules already get via planRun().
        $claimed = [];

        foreach ($rules as $rule) {
            if ($rule->adAccounts->isEmpty()) {
                Log::warning("Optimization rule #{$rule->id} \"{$rule->name}\" has no ad accounts; skipping.");

                continue;
            }

            OptimizationProposal::where('meta_ads_optimization_rule_id', $rule->id)
                ->where('status', 'pending')
                ->delete();

            foreach ($rule->adAccounts as $account) {
                // Drop targets a higher-priority rule already claimed this run.
                $proposals = array_values(array_filter(
                    $evaluator->plan($rule, $account),
                    function (array $proposal) use (&$claimed) {
                        $key = $proposal['target_type'].':'.$proposal['target_id'];
                        if (isset($claimed[$key])) {
                            return false;
                        }
                        $claimed[$key] = true;

                        return true;
                    },
                ));

                foreach ($proposals as $proposal) {
                    OptimizationProposal::create([
                        'workspace_id' => $rule->workspace_id,
                        'meta_ads_optimization_rule_id' => $rule->id,
                        'meta_ads_account_id' => $account->id,
                        'target_type' => $proposal['target_type'],
                        'target_id' => $proposal['target_id'],
                        'target_name' => $proposal['target_name'],
                        'action' => $proposal['action'],
                        'current_value' => $proposal['current_value'],
                        'new_value' => $proposal['new_value'],
                        'conditions_snapshot' => $proposal['conditions_snapshot'],
                        'status' => 'pending',
                    ]);

                    $created++;
                }
            }
        }

        return $created;
    }
}
