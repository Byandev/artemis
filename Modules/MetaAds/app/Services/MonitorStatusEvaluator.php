<?php

namespace Modules\MetaAds\Services;

use Illuminate\Support\Collection;
use Modules\MetaAds\Models\MonitorStatusRule;

/**
 * Pure status evaluator. Given an entity's per-window metric sums and a
 * workspace's status rules, it returns the suggested status plus the per-condition
 * "reason" snapshot explaining the decision. No DB or API access — the caller
 * supplies the aggregated numbers (see EntityMonitorService).
 */
class MonitorStatusEvaluator
{
    /** Ratios derived from the raw insight sums. Everything else is a raw column. */
    private const COMPUTED_METRICS = [
        'roas', 'cpa', 'cpc', 'ctr', 'cpm', 'cost_per_lead', 'cost_per_messaging_conversation',
    ];

    /** A test needs at least this many days of spend before we call it. */
    private const MIN_DAYS_TO_JUDGE = 3;

    /**
     * @param  array<string, array<string, float>>  $windowSums  keyed by window
     *                                                           (first_3_days|first_7_days|lifetime) → metric column sums
     * @param  Collection<int, MonitorStatusRule>  $rules  eager-loaded with conditions
     * @return array{status: string, reason: array<int, array<string, mixed>>}
     */
    public function evaluate(array $windowSums, Collection $rules, int $daysWithSpend): array
    {
        if ($daysWithSpend < self::MIN_DAYS_TO_JUDGE) {
            return ['status' => 'too_early', 'reason' => []];
        }

        // Priority order: scaling → maintain → killed. First rule that passes wins.
        foreach (MonitorStatusRule::STATUSES as $status) {
            $rule = $rules->first(fn (MonitorStatusRule $r) => $r->status === $status);

            if (! $rule || ! $rule->is_active || $rule->conditions->isEmpty()) {
                continue;
            }

            [$passed, $reason] = $this->evaluateRule($rule, $windowSums);

            if ($passed) {
                return ['status' => $status, 'reason' => $reason];
            }
        }

        return ['status' => 'unmatched', 'reason' => []];
    }

    /**
     * @param  array<string, array<string, float>>  $windowSums
     * @return array{0: bool, 1: array<int, array<string, mixed>>}
     */
    private function evaluateRule(MonitorStatusRule $rule, array $windowSums): array
    {
        $snapshot = [];

        foreach ($rule->conditions as $condition) {
            $sums = $windowSums[$condition->window] ?? [];
            $actual = $this->metricValue($condition->metric, $sums);
            $passed = $actual !== null && $this->checkOperator($actual, $condition->operator, (float) $condition->value);

            $snapshot[] = [
                'metric' => $condition->metric,
                'window' => $condition->window,
                'operator' => $condition->operator,
                'threshold' => (float) $condition->value,
                'actual_value' => $actual,
                'passed' => $passed,
            ];
        }

        $passed = $rule->condition_operator === 'or'
            ? collect($snapshot)->contains(fn ($c) => $c['passed'])
            : collect($snapshot)->every(fn ($c) => $c['passed']);

        return [$passed, $snapshot];
    }

    /**
     * Resolve a metric from a window's summed insight columns. Derived ratios are
     * computed; raw columns are read straight through. Null when the metric can't
     * be computed (e.g. ROAS with zero spend) so a condition simply doesn't pass.
     *
     * @param  array<string, float>  $s
     */
    public function metricValue(string $metric, array $s): ?float
    {
        $spend = (float) ($s['spend'] ?? 0);
        $impressions = (float) ($s['impressions'] ?? 0);
        $clicks = (float) ($s['clicks'] ?? 0);

        if (in_array($metric, self::COMPUTED_METRICS, true)) {
            return match ($metric) {
                'roas' => $spend > 0 ? (float) ($s['purchase_value'] ?? 0) / $spend : null,
                'cpa' => (float) ($s['conversions'] ?? 0) > 0 ? $spend / (float) $s['conversions'] : null,
                'cpc' => $clicks > 0 ? $spend / $clicks : null,
                'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : null,
                'cpm' => $impressions > 0 ? ($spend / $impressions) * 1000 : null,
                'cost_per_lead' => (float) ($s['leads'] ?? 0) > 0 ? $spend / (float) $s['leads'] : null,
                'cost_per_messaging_conversation' => (float) ($s['messaging_conversations_started'] ?? 0) > 0
                    ? $spend / (float) $s['messaging_conversations_started']
                    : null,
                default => null,
            };
        }

        return array_key_exists($metric, $s) ? (float) $s[$metric] : null;
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
}
