<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleLog;
use Modules\MetaAds\Models\OptimizationTargetClaim;
use Modules\MetaAds\Services\MetaGraphClient;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;
use Throwable;

/**
 * Apply a single optimization-rule decision to one campaign or ad set.
 *
 * One job per entity, dispatched by {@see EvaluateOptimizationRules}. Each call
 * to the Meta API is isolated so a failure on one target neither blocks nor
 * re-applies the others, and is retried independently.
 *
 * Before touching the API the job claims the target for the run: the first
 * rule's job to win the claim applies it, and any other rule's job for the same
 * campaign / ad set that run becomes a no-op. This guarantees a single entity is
 * never changed by two rules in one run, even under retries or overlapping runs.
 */
class ApplyOptimizationAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    /**
     * @param  array<int, array<string, mixed>>  $snapshot  per-condition results at decision time
     * @param  int|null  $proposalId  set when applying an approved proposal — marked `applied` on success
     */
    public function __construct(
        public readonly string $runId,
        public readonly OptimizationRule $rule,
        public readonly AdAccount $adAccount,
        public readonly Campaign|AdSet $target,
        public readonly array $snapshot,
        public readonly ?int $proposalId = null,
    ) {}

    public function handle(): void
    {
        // Optimization actions mutate live Meta entities (budgets, pause/enable).
        // Only ever hit the real Ads API from production — on staging/local this
        // is a no-op so we can never change a client's live campaigns by mistake.
        // The target is left unclaimed and proposals stay un-applied.
        if (! app()->environment('production')) {
            Log::info('Skipping optimization action outside production', [
                'environment' => app()->environment(),
                'rule_id' => $this->rule->id,
                'target_type' => $this->rule->target_type,
                'target_id' => $this->target->getKey(),
                'action' => $this->rule->action,
                'proposal_id' => $this->proposalId,
            ]);

            return;
        }

        $claim = $this->claimTarget();

        // Another rule already owns this campaign / ad set for this run.
        if ($claim === null) {
            return;
        }

        // Our own earlier attempt already applied — don't reapply (a retry must
        // not, e.g., increase a budget a second time).
        if ($claim->applied_at !== null) {
            return;
        }

        $client = $this->adAccount->graphClient(true);
        
        $fbId = (string) $this->target->getKey();
        $isBudget = in_array($this->rule->action, ['increase_budget', 'decrease_budget'], true);

        $previousValue = null;
        $newValue = null;

        if ($this->rule->action === 'pause') {
            $client->post($fbId, ['status' => 'PAUSED']);
            $this->target->update(['status' => 'PAUSED', 'effective_status' => 'PAUSED']);
        } elseif ($this->rule->action === 'enable') {
            $client->post($fbId, ['status' => 'ACTIVE']);
            $this->target->update(['status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);
        } elseif ($isBudget) {
            [$previousValue, $newValue] = $this->applyBudgetChange($client);
        }

        // Mark the claim applied before logging so a retry can't reapply.
        $claim->update(['applied_at' => now()]);

        // When applying an approved proposal, reflect that it's now executed.
        if ($this->proposalId !== null) {
            OptimizationProposal::where('id', $this->proposalId)->update(['status' => 'applied']);
        }

        // A budget action with no budget on the target changed nothing — the
        // claim is still spent, but there's no trigger to log.
        if ($isBudget && $previousValue === null) {
            return;
        }

        OptimizationRuleLog::create([
            'meta_ads_optimization_rule_id' => $this->rule->id,
            'workspace_id' => $this->rule->workspace_id,
            'target_type' => $this->rule->target_type,
            'target_id' => $this->target->getKey(),
            'target_name' => $this->target->name ?? null,
            'action_taken' => $this->rule->action,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'conditions_snapshot' => $this->snapshot,
            'triggered_at' => now(),
        ]);
    }

    /**
     * Atomically claim this target for the run. Returns the claim only when it
     * belongs to this rule (a fresh win, or this rule's own retry); returns null
     * when another rule already owns the target this run.
     */
    private function claimTarget(): ?OptimizationTargetClaim
    {
        $targetId = (string) $this->target->getKey();

        // insertOrIgnore + the unique (run_id, target_type, target_id) key makes
        // the first writer the sole owner; concurrent writers are ignored.
        DB::table('meta_ads_optimization_target_claims')->insertOrIgnore([
            'run_id' => $this->runId,
            'workspace_id' => $this->rule->workspace_id,
            'meta_ads_optimization_rule_id' => $this->rule->id,
            'target_type' => $this->rule->target_type,
            'target_id' => $targetId,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $claim = OptimizationTargetClaim::where('run_id', $this->runId)
            ->where('target_type', $this->rule->target_type)
            ->where('target_id', $targetId)
            ->first();

        return $claim && $claim->meta_ads_optimization_rule_id === $this->rule->id
            ? $claim
            : null;
    }

    /**
     * @return array{0: float|null, 1: float|null} [previousBudget, newBudget]
     */
    private function applyBudgetChange(MetaGraphClient $client): array
    {
        // Use daily_budget if set, otherwise lifetime_budget.
        $budgetField = $this->target->daily_budget !== null ? 'daily_budget' : 'lifetime_budget';
        $currentBudget = (float) ($this->target->{$budgetField} ?? 0);

        if ($currentBudget <= 0) {
            return [null, null];
        }

        $newBudget = OptimizationRuleEvaluator::computeNewBudget($this->rule, $currentBudget);

        // Meta API expects budget in minor units (cents).
        $client->post((string) $this->target->getKey(), [
            $budgetField => (int) round($newBudget * 100),
        ]);

        $this->target->update([$budgetField => $newBudget]);

        return [$currentBudget, $newBudget];
    }

    public function failed(Throwable $e): void
    {
        Log::error('OptimizationRule action failed', [
            'rule_id' => $this->rule->id,
            'target_id' => $this->target->getKey(),
            'action' => $this->rule->action,
            'error' => $e->getMessage(),
        ]);
    }
}
