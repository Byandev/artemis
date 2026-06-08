<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

class EvaluateOptimizationRulesCommand extends Command
{
    protected $signature = 'meta-ads:evaluate-optimization-rules';

    protected $description = 'Evaluate active Meta Ads optimization rules and list the campaigns/ad sets that would be affected. Does NOT apply any changes.';

    public function handle(OptimizationRuleEvaluator $evaluator): int
    {
        $rules = OptimizationRule::query()
            ->where('is_active', true)
            ->with(['adAccounts', 'conditions'])
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active optimization rules.');

            return self::SUCCESS;
        }

        $totalAffected = 0;

        foreach ($rules as $rule) {
            if ($rule->adAccounts->isEmpty()) {
                $this->warn("Rule #{$rule->id} \"{$rule->name}\" has no ad accounts; skipping.");

                continue;
            }

            // Refresh this rule's pending queue so it reflects the latest run.
            // Decided proposals (approved/rejected/applied) are kept as history.
            OptimizationProposal::where('meta_ads_optimization_rule_id', $rule->id)
                ->where('status', 'pending')
                ->delete();

            foreach ($rule->adAccounts as $account) {
                $proposals = $evaluator->plan($rule, $account);

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
                }

                $this->newLine();
                $this->line(sprintf(
                    '<info>%s</info> (#%d) · %s · %s',
                    $rule->name,
                    $rule->id,
                    $account->name,
                    strtoupper($rule->execution_mode),
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

                $totalAffected += count($proposals);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Dry run complete. %d target(s) would be affected. No changes were applied.',
            $totalAffected,
        ));

        return self::SUCCESS;
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
