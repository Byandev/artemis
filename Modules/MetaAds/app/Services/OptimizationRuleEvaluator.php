<?php

namespace Modules\MetaAds\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationRule;

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

    /**
     * Decide what every rule would change this run, guaranteeing each campaign /
     * ad set is acted on at most once: the first rule (in the given order) that
     * matches a target claims it, and later rules skip that target.
     *
     * No API calls or writes happen here — it only reads metrics and returns the
     * decisions to dispatch. Each decision is applied by its own queued job.
     *
     * @param  iterable<OptimizationRule>  $rules  eager-loaded with conditions + adAccounts
     * @return array<int, array{rule: OptimizationRule, adAccount: AdAccount, target: Campaign|AdSet, snapshot: array<int, array<string, mixed>>}>
     */
    public function planRun(iterable $rules): array
    {
        $claimed = [];
        $decisions = [];

        foreach ($rules as $rule) {
            foreach ($rule->adAccounts as $adAccount) {
                foreach ($this->resolveTargets($rule, $adAccount) as $target) {
                    $key = $rule->target_type.':'.$target->getKey();

                    if (isset($claimed[$key])) {
                        continue; // already claimed by an earlier rule this run
                    }

                    $snapshot = $this->buildSnapshot($rule, $target);

                    if (! $this->allConditionsMet($snapshot, $rule->condition_operator)) {
                        continue;
                    }

                    $claimed[$key] = true;
                    $decisions[] = compact('rule', 'adAccount', 'target', 'snapshot');
                }
            }
        }

        return $decisions;
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

            $proposal = $this->buildProposal($rule, $target, $snapshot);

            // Skip budget changes that wouldn't actually move the budget in the
            // rule's direction — e.g. a "decrease" whose result is clamped up to
            // budget_min above the current budget (or an "increase" clamped down
            // by budget_max). Such a proposal contradicts the rule's intent.
            if ($this->isNonMovingBudgetChange($rule, $proposal)) {
                continue;
            }

            $proposals[] = $proposal;
        }

        return $proposals;
    }

    /**
     * True when a budget proposal doesn't lower (for decrease) or raise (for
     * increase) the current budget — including the no-budget case.
     *
     * @param  array<string, mixed>  $proposal
     */
    private function isNonMovingBudgetChange(OptimizationRule $rule, array $proposal): bool
    {
        if (! in_array($rule->action, ['increase_budget', 'decrease_budget'], true)) {
            return false;
        }

        $current = $proposal['current_value'];
        $new = $proposal['new_value'];

        if ($current === null || $new === null) {
            return true; // no budget to act on
        }

        return $rule->action === 'decrease_budget'
            ? $new >= $current   // a "decrease" that doesn't lower the budget
            : $new <= $current;  // an "increase" that doesn't raise the budget
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
            // "budget", "running_days" and "last_modified_in_hours" live on the
            // campaign / ad set itself, not in insights, so they're read straight
            // off the target and ignore the time window. Everything else is an
            // insights metric.
            $actualValue = match ($condition->metric) {
                'budget' => $this->targetBudget($target),
                'running_days' => $this->targetRunningDays($target),
                'last_modified_in_hours' => $this->targetLastModifiedHours($target),
                default => $this->computeMetric(
                    $condition->metric,
                    $rule->target_type,
                    $target->id,
                    $condition->time_window,
                ),
            };

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
            $newBudget = $currentBudget > 0 ? self::computeNewBudget($rule, $currentBudget) : null;
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

    /**
     * Current budget of a campaign or ad set (major units), preferring the daily
     * budget and falling back to the lifetime budget. Null when the target has
     * no budget of its own.
     */
    private function targetBudget(Campaign|AdSet $target): ?float
    {
        $budget = $target->daily_budget ?? $target->lifetime_budget;

        return $budget !== null ? (float) $budget : null;
    }

    /**
     * Whole days the campaign / ad set has been running, from its start_time
     * (falling back to created_time). Null when neither is set. e.g. a rule
     * "running_days >= 3" only fires once the entity has been live 3+ days.
     */
    private function targetRunningDays(Campaign|AdSet $target): ?float
    {
        $start = $target->start_time ?? $target->created_time;

        if ($start === null) {
            return null;
        }

        $start = $start instanceof Carbon ? $start : Carbon::parse($start);

        return (float) $start->startOfDay()->diffInDays(Carbon::now()->startOfDay());
    }

    /**
     * Whole hours since the campaign / ad set was last modified on Meta
     * (`updated_time`, falling back to created_time). Null when neither is set.
     * Useful as a cooldown, e.g. "last_modified_in_hours >= 24" so a rule won't
     * touch something that was just changed.
     */
    private function targetLastModifiedHours(Campaign|AdSet $target): ?float
    {
        $modified = $target->updated_time ?? $target->created_time;

        if ($modified === null) {
            return null;
        }

        $modified = $modified instanceof Carbon ? $modified : Carbon::parse($modified);

        return (float) $modified->diffInHours(Carbon::now());
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
    // Budget calculation
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the new budget for a budget action, applying the percentage cap
     * and min/max clamps. Pure — no API or DB side effects. Shared by the
     * proposal dry-run and the queued apply job.
     */
    public static function computeNewBudget(OptimizationRule $rule, float $currentBudget): float
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
