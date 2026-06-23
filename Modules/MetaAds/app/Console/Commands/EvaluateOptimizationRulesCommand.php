<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Modules\MetaAds\Jobs\EvaluateDueOptimizationRules;
use Modules\MetaAds\Jobs\MarkOptimizationRunStep;
use Modules\MetaAds\Jobs\SyncAds;
use Modules\MetaAds\Jobs\SyncAdSets;
use Modules\MetaAds\Jobs\SyncCampaigns;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Jobs\SyncMetaAdAccounts;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRun;
use Throwable;

class EvaluateOptimizationRulesCommand extends Command
{
    protected $signature = 'meta-ads:evaluate-optimization-rules
        {--skip-sync : Evaluate already-synced data immediately, without first refreshing from Meta}';

    protected $description = 'Refresh today\'s ad accounts, campaigns, ad sets, ads and insights, then evaluate the due Meta Ads optimization rules on that fresh data.';

    public function handle(): int
    {
        $now = Carbon::now();

        $rules = OptimizationRule::query()
            ->where('is_active', true)
            ->with(['adAccounts', 'conditions'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active optimization rules.');

            return self::SUCCESS;
        }

        // Each rule runs on its own user-chosen schedule; only evaluate the ones
        // that are due this hour.
        $rules = $rules->filter(fn (OptimizationRule $rule) => $rule->isDue($now))->values();

        if ($rules->isEmpty()) {
            $this->info('No optimization rules are due to run.');

            return self::SUCCESS;
        }

        $skipSync = (bool) $this->option('skip-sync');
        $runsQueued = 0;

        // Group by workspace so each workspace's sync is isolated (a failure in
        // one doesn't stop the others). Within a workspace every rule is still
        // tracked + evaluated independently, but the sync is shared: each ad
        // account is refreshed only once even when several rules use it.
        foreach ($rules->groupBy('workspace_id') as $workspaceRules) {
            $runsQueued += $skipSync
                ? $this->queueEvaluationOnly($workspaceRules, $now)
                : $this->queueSyncThenEvaluate($workspaceRules, $now);
        }

        if ($runsQueued === 0) {
            $this->warn('Nothing queued — due rules had no ad accounts to sync.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Queued %d independent rule run(s)%s. Track each rule on its optimization-rules page.',
            $runsQueued,
            $skipSync ? ' (sync skipped)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Pure-evaluation path: skip the sync and check whatever is already in the
     * database (used by tests and for re-checking without a refresh). Each rule
     * is still its own single-phase run.
     *
     * @param  Collection<int, OptimizationRule>  $rules
     * @return int number of runs queued
     */
    private function queueEvaluationOnly(Collection $rules, Carbon $now): int
    {
        foreach ($rules as $rule) {
            $run = $this->createRun($rule, [OptimizationRun::STEP_EVALUATE], 0);

            EvaluateDueOptimizationRules::dispatch([$rule->id], $run->id)->onQueue('meta-ads');
            $this->markEvaluated($rule, $now);
        }

        return $rules->count();
    }

    /**
     * Full path for one workspace: refresh each distinct ad account ONCE, then
     * evaluate every rule on that fresh data. Each rule gets its own tracked run,
     * but they share the single sync pass so a shared account isn't re-synced.
     *
     * @param  Collection<int, OptimizationRule>  $rules
     * @return int number of runs queued
     */
    private function queueSyncThenEvaluate(Collection $rules, Carbon $now): int
    {
        // Rules with no ad accounts have nothing to sync or evaluate.
        $rules->filter(fn (OptimizationRule $rule) => $rule->adAccounts->isEmpty())
            ->each(fn (OptimizationRule $rule) => $this->warn("Rule #{$rule->id} \"{$rule->name}\" has no ad accounts; skipping."));

        $rules = $rules->filter(fn (OptimizationRule $rule) => $rule->adAccounts->isNotEmpty())->values();

        if ($rules->isEmpty()) {
            return 0;
        }

        // The distinct accounts across every rule — synced once, shared by all.
        $accounts = $rules->flatMap->adAccounts->unique('id')->values();

        // One run per rule; all advance together through the shared sync phases.
        $runIdByRuleId = [];
        foreach ($rules as $rule) {
            $runIdByRuleId[$rule->id] = $this->createRun($rule, [
                OptimizationRun::STEP_AD_ACCOUNTS,
                OptimizationRun::STEP_CAMPAIGNS,
                OptimizationRun::STEP_AD_SETS,
                OptimizationRun::STEP_ADS,
                OptimizationRun::STEP_INSIGHTS,
                OptimizationRun::STEP_EVALUATE,
            ], $rule->adAccounts->count())->id;
        }

        $runIds = array_values($runIdByRuleId);
        $today = Carbon::today()->toDateString();

        // Phase-grouped chain: a marker advances ALL the runs onto each phase,
        // then that phase's jobs run once per distinct account before the next
        // marker. Finally each rule is evaluated into its own run.
        $chain = [new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_AD_ACCOUNTS)];

        foreach ($accounts->flatMap->metaUsers->unique('id') as $metaUser) {
            $chain[] = new SyncMetaAdAccounts($metaUser);
        }

        $chain[] = new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_CAMPAIGNS);
        foreach ($accounts as $account) {
            $chain[] = new SyncCampaigns($account);
        }

        $chain[] = new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_AD_SETS);
        foreach ($accounts as $account) {
            $chain[] = new SyncAdSets($account);
        }

        $chain[] = new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_ADS);
        foreach ($accounts as $account) {
            $chain[] = new SyncAds($account);
        }

        $chain[] = new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_INSIGHTS);
        foreach ($accounts as $account) {
            $chain[] = new SyncInsights($account, $today);
        }

        $chain[] = new MarkOptimizationRunStep($runIds, OptimizationRun::STEP_EVALUATE);
        foreach ($rules as $rule) {
            $chain[] = new EvaluateDueOptimizationRules([$rule->id], $runIdByRuleId[$rule->id]);
        }

        Bus::chain($chain)
            ->onQueue('meta-ads')
            // A permanently-failed sync stops the chain — fail every run still in
            // flight so their panels show the break instead of spinning forever.
            ->catch(function (Throwable $e) use ($runIds) {
                OptimizationRun::whereIn('id', $runIds)
                    ->where('status', OptimizationRun::STATUS_RUNNING)
                    ->update([
                        'status' => OptimizationRun::STATUS_FAILED,
                        'finished_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]);
            })
            ->dispatch();

        $rules->each(fn (OptimizationRule $rule) => $this->markEvaluated($rule, $now));

        return $rules->count();
    }

    /**
     * @param  array<int, string>  $stepKeys
     */
    private function createRun(OptimizationRule $rule, array $stepKeys, int $totalAccounts): OptimizationRun
    {
        return OptimizationRun::create([
            'workspace_id' => $rule->workspace_id,
            'meta_ads_optimization_rule_id' => $rule->id,
            'status' => OptimizationRun::STATUS_RUNNING,
            'current_step' => $stepKeys[0],
            'steps' => OptimizationRun::planFor($stepKeys),
            'total_rules' => 1,
            'total_accounts' => $totalAccounts,
            'started_at' => Carbon::now(),
        ]);
    }

    private function markEvaluated(OptimizationRule $rule, Carbon $at): void
    {
        $rule->forceFill(['last_evaluated_at' => $at])->save();
    }
}
