<?php

namespace Modules\MetaAds\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleLog;

class OptimizationRuleEvaluator
{
    // Computed metrics derived from raw insight columns.
    private const COMPUTED_METRICS = [
        'roas',
        'cpa',
        'cpc',
        'ctr',
        'cpm',
        'cost_per_lead',
        'cost_per_messaging_conversation',
    ];

    public function evaluate(OptimizationRule $rule, AdAccount $adAccount): void
    {
        foreach ($this->resolveTargets($rule, $adAccount) as $target) {
            $this->evaluateTarget($rule, $adAccount, $target);
        }
    }

    /**
     * Dry run: return the changes this rule *would* make to its targets without
     * touching the Meta API or local records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plan(OptimizationRule $rule, AdAccount $adAccount): array
    {
        $proposals = [];

        foreach ($this->resolveTargets($rule, $adAccount) as $target) {
            $snapshot = $this->buildSnapshot($rule, $target);

            if (! $this->allConditionsMet($snapshot, $rule->condition_operator)) {
                continue;
            }

            $proposals[] = $this->buildProposal($rule, $target, $snapshot);
        }

        return $proposals;
    }

    private function evaluateTarget(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target): void
    {
        $snapshot = $this->buildSnapshot($rule, $target);

        if (! $this->allConditionsMet($snapshot, $rule->condition_operator)) {
            return;
        }

        $this->applyAction($rule, $adAccount, $target, $snapshot);
    }

    /**
     * Evaluate each condition for a target and return the per-condition results.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSnapshot(OptimizationRule $rule, Campaign|AdSet $target): array
    {
        $snapshot = [];

        foreach ($rule->conditions as $condition) {
            $actualValue = $this->computeMetric(
                $condition->metric,
                $rule->target_type,
                $target->id,
                $condition->time_window,
            );

            $snapshot[] = [
                'metric' => $condition->metric,
                'operator' => $condition->operator,
                'threshold' => (float) $condition->value,
                'actual_value' => $actualValue,
                'time_window' => $condition->time_window,
                'passed' => $actualValue !== null && $this->checkOperator(
                    $actualValue,
                    $condition->operator,
                    (float) $condition->value,
                ),
            ];
        }

        return $snapshot;
    }

    /**
     * Describe the change a triggered rule would apply to a target.
     *
     * @param  array<int, array<string, mixed>>  $snapshot
     * @return array<string, mixed>
     */
    private function buildProposal(OptimizationRule $rule, Campaign|AdSet $target, array $snapshot): array
    {
        $currentBudget = null;
        $newBudget = null;

        if (in_array($rule->action, ['increase_budget', 'decrease_budget'], true)) {
            $budgetField = $target->daily_budget !== null ? 'daily_budget' : 'lifetime_budget';
            $currentBudget = (float) ($target->{$budgetField} ?? 0);
            $newBudget = $currentBudget > 0 ? $this->computeNewBudget($rule, $currentBudget) : null;
        }

        return [
            'target_type' => $rule->target_type,
            'target_id' => (string) $target->id,
            'target_name' => $target->name ?? null,
            'action' => $rule->action,
            'current_value' => $currentBudget,
            'new_value' => $newBudget,
            'conditions_snapshot' => $snapshot,
        ];
    }

    private function allConditionsMet(array $snapshot, string $operator): bool
    {
        if ($operator === 'and') {
            return collect($snapshot)->every(fn ($c) => $c['passed']);
        }

        // or
        return collect($snapshot)->contains(fn ($c) => $c['passed']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Metric computation
    // ──────────────────────────────────────────────────────────────────────────

    private function computeMetric(string $metric, string $targetType, string|int $targetId, string $timeWindow): ?float
    {
        $dateRange = $this->resolveDateRange($timeWindow);

        $query = DB::table('meta_ads_insights');

        if ($dateRange !== null) {
            $query->whereBetween('date', [$dateRange['from'], $dateRange['to']]);
        }

        if ($targetType === 'campaign') {
            $query->where('meta_ads_campaign_id', $targetId);
        } else {
            $query->where('meta_ads_set_id', $targetId);
        }

        if (in_array($metric, self::COMPUTED_METRICS, true)) {
            return $this->computeDerivedMetric($metric, $query);
        }

        // Raw column — just SUM it.
        $result = (clone $query)->selectRaw("SUM(`{$metric}`) as value")->value('value');

        return $result !== null ? (float) $result : null;
    }

    private function computeDerivedMetric(string $metric, Builder $query): ?float
    {
        $row = (clone $query)->selectRaw('
            SUM(spend) as spend,
            SUM(impressions) as impressions,
            SUM(clicks) as clicks,
            SUM(purchases) as purchases,
            SUM(purchase_value) as purchase_value,
            SUM(leads) as leads,
            SUM(messaging_conversations_started) as messaging_conversations_started,
            SUM(conversions) as conversions
        ')->first();

        if (! $row) {
            return null;
        }

        $spend = (float) ($row->spend ?? 0);
        $impressions = (float) ($row->impressions ?? 0);
        $clicks = (float) ($row->clicks ?? 0);

        return match ($metric) {
            'roas' => $spend > 0 ? (float) ($row->purchase_value ?? 0) / $spend : null,
            'cpa' => (float) ($row->conversions ?? 0) > 0 ? $spend / (float) $row->conversions : null,
            'cpc' => $clicks > 0 ? $spend / $clicks : null,
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : null,
            'cpm' => $impressions > 0 ? ($spend / $impressions) * 1000 : null,
            'cost_per_lead' => (float) ($row->leads ?? 0) > 0 ? $spend / (float) $row->leads : null,
            'cost_per_messaging_conversation' => (float) ($row->messaging_conversations_started ?? 0) > 0
                                                    ? $spend / (float) $row->messaging_conversations_started
                                                    : null,
            default => null,
        };
    }

    private function resolveDateRange(string $timeWindow): ?array
    {
        $today = Carbon::today();

        return match ($timeWindow) {
            'today' => ['from' => $today->toDateString(), 'to' => $today->toDateString()],
            'yesterday' => ['from' => $today->copy()->subDay()->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            // "including today" → 3 days total is today and the 2 days before it.
            'last_3_days' => ['from' => $today->copy()->subDays(2)->toDateString(), 'to' => $today->toDateString()],
            'last_7_days' => ['from' => $today->copy()->subDays(6)->toDateString(), 'to' => $today->toDateString()],
            // "previous" windows exclude today — they end yesterday.
            'previous_3_days' => ['from' => $today->copy()->subDays(3)->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            'previous_7_days' => ['from' => $today->copy()->subDays(7)->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            default => null,
        };
    }

    private function checkOperator(float $actual, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $actual > $threshold,
            '<' => $actual < $threshold,
            '>=' => $actual >= $threshold,
            '<=' => $actual <= $threshold,
            '=' => abs($actual - $threshold) < 0.0001,
            default => false,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Action application
    // ──────────────────────────────────────────────────────────────────────────

    private function applyAction(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target, array $snapshot): void
    {
        $client = $adAccount->graphClient();
        $fbId = (string) $target->id;

        $previousValue = null;
        $newValue = null;

        try {
            if ($rule->action === 'pause') {
                $client->post($fbId, ['status' => 'PAUSED']);
                $target->update(['status' => 'PAUSED', 'effective_status' => 'PAUSED']);

            } elseif ($rule->action === 'enable') {
                $client->post($fbId, ['status' => 'ACTIVE']);
                $target->update(['status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);

            } elseif (in_array($rule->action, ['increase_budget', 'decrease_budget'], true)) {
                [$previousValue, $newValue] = $this->applyBudgetChange($rule, $adAccount, $target, $client);
            }
        } catch (\Throwable $e) {
            Log::error('OptimizationRule action failed', [
                'rule_id' => $rule->id,
                'target_id' => $fbId,
                'action' => $rule->action,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        OptimizationRuleLog::create([
            'meta_ads_optimization_rule_id' => $rule->id,
            'workspace_id' => $rule->workspace_id,
            'target_type' => $rule->target_type,
            'target_id' => $target->id,
            'target_name' => $target->name ?? null,
            'action_taken' => $rule->action,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'conditions_snapshot' => $snapshot,
            'triggered_at' => now(),
        ]);
    }

    private function applyBudgetChange(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target, MetaGraphClient $client): array
    {
        // Use daily_budget if set, otherwise lifetime_budget.
        $budgetField = $target->daily_budget !== null ? 'daily_budget' : 'lifetime_budget';
        $currentBudget = (float) ($target->{$budgetField} ?? 0);

        if ($currentBudget <= 0) {
            return [null, null];
        }

        $newBudget = $this->computeNewBudget($rule, $currentBudget);

        // Meta API expects budget in minor units (cents).
        $client->post((string) $target->id, [
            $budgetField => (int) round($newBudget * 100),
        ]);

        $target->update([$budgetField => $newBudget]);

        return [$currentBudget, $newBudget];
    }

    /**
     * Resolve the new budget for a budget action, applying the percentage cap
     * and min/max clamps. Pure — no API or DB side effects.
     */
    private function computeNewBudget(OptimizationRule $rule, float $currentBudget): float
    {
        $adjustment = (float) $rule->adjustment_value;

        // Resolve the absolute amount to add/remove. Percentage adjustments can
        // be capped by max_adjustment_amount; "specific amount" is the delta itself.
        if ($rule->adjustment_type === 'percentage') {
            $delta = $currentBudget * ($adjustment / 100);

            if ($rule->max_adjustment_amount !== null) {
                $delta = min($delta, (float) $rule->max_adjustment_amount);
            }
        } else {
            $delta = $adjustment;
        }

        $newBudget = match ($rule->action) {
            'increase_budget' => $currentBudget + $delta,
            'decrease_budget' => $currentBudget - $delta,
            default => $currentBudget,
        };

        // Clamp to configured min/max.
        if ($rule->budget_min !== null) {
            $newBudget = max((float) $rule->budget_min, $newBudget);
        }
        if ($rule->budget_max !== null) {
            $newBudget = min((float) $rule->budget_max, $newBudget);
        }

        return $newBudget;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Target resolution
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveTargets(OptimizationRule $rule, AdAccount $adAccount): Collection
    {
        if ($rule->target_type === 'campaign') {
            return $adAccount->campaigns()->get();
        }

        return $adAccount->adSets()->get();
    }
}
