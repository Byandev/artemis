<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\MetaAds\Jobs\ApplyOptimizationAction;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

class EvaluateOptimizationRulesCommand extends Command
{
    protected $signature = 'meta-ads:evaluate-optimization-rules';

    protected $description = 'Evaluate active Meta Ads optimization rules: apply automatic rules (one queued job per entity) and refresh proposals for approval-mode rules.';

    public function handle(OptimizationRuleEvaluator $evaluator): int
    {
        $rules = OptimizationRule::query()
            ->where('is_active', true)
            ->with(['adAccounts', 'conditions'])
            // When two rules contest a target, the higher priority claims it;
            // ties fall back to the older rule for a deterministic result.
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active optimization rules.');

            return self::SUCCESS;
        }

        [$automatic, $approval] = $rules->partition(
            fn (OptimizationRule $rule) => $rule->execution_mode === 'automatic',
        );

        // One id for the whole run — the apply jobs use it to claim each target
        // so no campaign / ad set is changed by more than one rule this run.
        $runId = (string) Str::uuid();

        $dispatched = $this->applyAutomatic($evaluator, $automatic, $runId);
        $proposed = $this->proposeForApproval($evaluator, $approval);

        $this->newLine();
        $this->info(sprintf(
            'Done. %d automatic change(s) dispatched · %d proposal(s) queued for approval.',
            $dispatched,
            $proposed,
        ));

        return self::SUCCESS;
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

        $this->newLine();

        if (empty($decisions)) {
            $this->line('<comment>Automatic:</comment> no campaigns/ad sets matched.');

            return 0;
        }

        foreach ($decisions as $decision) {
            ApplyOptimizationAction::dispatch(
                $runId,
                $decision['rule'],
                $decision['adAccount'],
                $decision['target'],
                $decision['snapshot'],
            )->onQueue('meta-ads');
        }

        $this->line('<info>Automatic — dispatched apply jobs:</info>');
        $this->table(
            ['Rule', 'Type', 'ID', 'Name', 'Action'],
            array_map(fn (array $d) => [
                sprintf('%s (#%d)', $d['rule']->name, $d['rule']->id),
                $d['rule']->target_type,
                (string) $d['target']->getKey(),
                Str::limit($d['target']->name ?? '—', 40),
                $d['rule']->action,
            ], $decisions),
        );

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
                $this->warn("Rule #{$rule->id} \"{$rule->name}\" has no ad accounts; skipping.");

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

                $this->newLine();
                $this->line(sprintf(
                    '<info>%s</info> (#%d) · %s · APPROVAL',
                    $rule->name,
                    $rule->id,
                    $account->name,
                ));

                if (empty($proposals)) {
                    $this->line('  No campaigns/ad sets matched.');

                    continue;
                }

                $this->table(
                    ['Type', 'ID', 'Name', 'Action', 'Budget (current → new)'],
                    array_map(fn (array $p) => [
                        $p['target_type'],
                        $p['target_id'],
                        Str::limit($p['target_name'] ?? '—', 40),
                        $p['action'],
                        $this->formatBudgetChange($p),
                    ], $proposals),
                );
            }
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function formatBudgetChange(array $proposal): string
    {
        if (! in_array($proposal['action'], ['increase_budget', 'decrease_budget'], true)) {
            return '—';
        }

        if ($proposal['current_value'] === null || $proposal['new_value'] === null) {
            return 'n/a (no budget set)';
        }

        return number_format((float) $proposal['current_value'], 2)
            .' → '
            .number_format((float) $proposal['new_value'], 2);
    }
}
