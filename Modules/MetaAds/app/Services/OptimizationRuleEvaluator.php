<?php

namespace Modules\MetaAds\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleCondition;
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
        $targets = $this->resolveTargets($rule, $adAccount);

        foreach ($targets as $target) {
            $this->evaluateTarget($rule, $adAccount, $target);
        }
    }

    private function evaluateTarget(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target): void
    {
        $conditions = $rule->conditions;
        $snapshot = [];

        foreach ($conditions as $condition) {
            $actualValue = $this->computeMetric(
                $condition->metric,
                $rule->target_type,
                $target->id,
                $condition->time_window,
            );

            $passed = $actualValue !== null && $this->checkOperator(
                $actualValue,
                $condition->operator,
                (float) $condition->value,
            );

            $snapshot[] = [
                'metric'       => $condition->metric,
                'operator'     => $condition->operator,
                'threshold'    => (float) $condition->value,
                'actual_value' => $actualValue,
                'time_window'  => $condition->time_window,
                'passed'       => $passed,
            ];
        }

        $triggered = $this->allConditionsMet($snapshot, $rule->condition_operator);

        if (! $triggered) {
            return;
        }

        $this->applyAction($rule, $adAccount, $target, $snapshot);
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

    private function computeDerivedMetric(string $metric, \Illuminate\Database\Query\Builder $query): ?float
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

        $spend       = (float) ($row->spend ?? 0);
        $impressions = (float) ($row->impressions ?? 0);
        $clicks      = (float) ($row->clicks ?? 0);

        return match ($metric) {
            'roas'                            => $spend > 0 ? (float) ($row->purchase_value ?? 0) / $spend : null,
            'cpa'                             => (float) ($row->conversions ?? 0) > 0 ? $spend / (float) $row->conversions : null,
            'cpc'                             => $clicks > 0 ? $spend / $clicks : null,
            'ctr'                             => $impressions > 0 ? ($clicks / $impressions) * 100 : null,
            'cpm'                             => $impressions > 0 ? ($spend / $impressions) * 1000 : null,
            'cost_per_lead'                   => (float) ($row->leads ?? 0) > 0 ? $spend / (float) $row->leads : null,
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
            'today'        => ['from' => $today->toDateString(), 'to' => $today->toDateString()],
            'last_3_days'  => ['from' => $today->copy()->subDays(3)->toDateString(), 'to' => $today->toDateString()],
            'last_7_days'  => ['from' => $today->copy()->subDays(7)->toDateString(), 'to' => $today->toDateString()],
            'last_14_days' => ['from' => $today->copy()->subDays(14)->toDateString(), 'to' => $today->toDateString()],
            'last_30_days' => ['from' => $today->copy()->subDays(30)->toDateString(), 'to' => $today->toDateString()],
            'lifetime'     => null,
            default        => null,
        };
    }

    private function checkOperator(float $actual, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>'  => $actual > $threshold,
            '<'  => $actual < $threshold,
            '>=' => $actual >= $threshold,
            '<=' => $actual <= $threshold,
            '='  => abs($actual - $threshold) < 0.0001,
            default => false,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Action application
    // ──────────────────────────────────────────────────────────────────────────

    private function applyAction(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target, array $snapshot): void
    {
        $client = $adAccount->graphClient();
        $fbId   = (string) $target->id;

        $previousValue = null;
        $newValue      = null;

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
                'rule_id'   => $rule->id,
                'target_id' => $fbId,
                'action'    => $rule->action,
                'error'     => $e->getMessage(),
            ]);

            return;
        }

        OptimizationRuleLog::create([
            'meta_ads_optimization_rule_id' => $rule->id,
            'workspace_id'                  => $rule->workspace_id,
            'target_type'                   => $rule->target_type,
            'target_id'                     => $target->id,
            'target_name'                   => $target->name ?? null,
            'action_taken'                  => $rule->action,
            'previous_value'                => $previousValue,
            'new_value'                     => $newValue,
            'conditions_snapshot'           => $snapshot,
            'triggered_at'                  => now(),
        ]);
    }

    private function applyBudgetChange(OptimizationRule $rule, AdAccount $adAccount, Campaign|AdSet $target, MetaGraphClient $client): array
    {
        // Use daily_budget if set, otherwise lifetime_budget.
        $budgetField  = $target->daily_budget !== null ? 'daily_budget' : 'lifetime_budget';
        $currentBudget = (float) ($target->{$budgetField} ?? 0);

        if ($currentBudget <= 0) {
            return [null, null];
        }

        $adjustment = (float) $rule->adjustment_value;

        $newBudget = match ($rule->action) {
            'increase_budget' => $rule->adjustment_type === 'percentage'
                ? $currentBudget * (1 + $adjustment / 100)
                : $currentBudget + $adjustment,

            'decrease_budget' => $rule->adjustment_type === 'percentage'
                ? $currentBudget * (1 - $adjustment / 100)
                : $currentBudget - $adjustment,

            default => $currentBudget,
        };

        // Clamp to configured min/max.
        if ($rule->budget_min !== null) {
            $newBudget = max((float) $rule->budget_min, $newBudget);
        }
        if ($rule->budget_max !== null) {
            $newBudget = min((float) $rule->budget_max, $newBudget);
        }

        // Meta API expects budget in minor units (cents).
        $client->post((string) $target->id, [
            $budgetField => (int) round($newBudget * 100),
        ]);

        $target->update([$budgetField => $newBudget]);

        return [$currentBudget, $newBudget];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Target resolution
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveTargets(OptimizationRule $rule, AdAccount $adAccount): \Illuminate\Database\Eloquent\Collection
    {
        if ($rule->target_type === 'campaign') {
            return $adAccount->campaigns()->get();
        }

        return $adAccount->adSets()->get();
    }
}
