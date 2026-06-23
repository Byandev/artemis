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
use Modules\MetaAds\Models\AdAccount;
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
            // When two rules contest a target, the higher priority claims it;
            // ties fall back to the older rule for a deterministic result.
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

        // One tracked run + chain per workspace, so each workspace's progress is
        // independent and maps onto its own optimization-rules page.
        foreach ($rules->groupBy('workspace_id') as $workspaceId => $workspaceRules) {
            $workspaceId = (int) $workspaceId;
            $ruleIds = $workspaceRules->pluck('id')->all();

            if ($skipSync) {
                $this->queueEvaluationOnly($workspaceId, $ruleIds);
                $this->markEvaluated($ruleIds, $now);
                $runsQueued++;

                continue;
            }

            // Only the ad accounts the due rules actually target need refreshing.
            $adAccounts = $workspaceRules->flatMap->adAccounts->unique('id')->values();

            if ($adAccounts->isEmpty()) {
                $this->warn("Workspace #{$workspaceId}: due rules have no ad accounts; skipping.");

                continue;
            }

            $this->queueSyncThenEvaluate($workspaceId, $ruleIds, $adAccounts);
            $this->markEvaluated($ruleIds, $now);
            $runsQueued++;
        }

        if ($runsQueued === 0) {
            $this->warn('Nothing queued — due rules had no ad accounts to sync.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            "Queued %d optimization run(s)%s. Track each run's step on its workspace's optimization-rules page.",
            $runsQueued,
            $skipSync ? ' (sync skipped)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Pure-evaluation path: skip the sync and check whatever is already in the
     * database (used by tests and for re-checking without a refresh). Still
     * tracked as a run so it shows up in progress, just with a single phase.
     *
     * @param  array<int, int>  $ruleIds
     */
    private function queueEvaluationOnly(int $workspaceId, array $ruleIds): void
    {
        $run = OptimizationRun::create([
            'workspace_id' => $workspaceId,
            'status' => OptimizationRun::STATUS_RUNNING,
            'current_step' => OptimizationRun::STEP_EVALUATE,
            'steps' => OptimizationRun::planFor([OptimizationRun::STEP_EVALUATE]),
            'total_rules' => count($ruleIds),
            'total_accounts' => 0,
            'started_at' => Carbon::now(),
        ]);

        EvaluateDueOptimizationRules::dispatch($ruleIds, $run->id)->onQueue('meta-ads');
    }

    /**
     * Full path: refresh today's data, then evaluate. Built as one chain so the
     * steps run strictly in order and the evaluation always runs LAST.
     *
     * @param  array<int, int>  $ruleIds
     * @param  Collection<int, AdAccount>  $adAccounts
     */
    private function queueSyncThenEvaluate(int $workspaceId, array $ruleIds, Collection $adAccounts): void
    {
        $today = Carbon::today()->toDateString();

        $run = OptimizationRun::create([
            'workspace_id' => $workspaceId,
            'status' => OptimizationRun::STATUS_RUNNING,
            'current_step' => OptimizationRun::STEP_AD_ACCOUNTS,
            'steps' => OptimizationRun::planFor([
                OptimizationRun::STEP_AD_ACCOUNTS,
                OptimizationRun::STEP_CAMPAIGNS,
                OptimizationRun::STEP_AD_SETS,
                OptimizationRun::STEP_ADS,
                OptimizationRun::STEP_INSIGHTS,
                OptimizationRun::STEP_EVALUATE,
            ]),
            'total_rules' => count($ruleIds),
            'total_accounts' => $adAccounts->count(),
            'started_at' => Carbon::now(),
        ]);

        $runId = $run->id;

        // Phase-grouped chain: a marker advances the run onto each phase, then
        // that phase's jobs run for every account before the next marker.
        //   per MetaUser: refresh the ad account list
        //   per account:  campaigns -> ad sets -> ads -> today's insights
        //   finally:      evaluate the due rules (marks the run complete)
        $chain = [new MarkOptimizationRunStep($runId, OptimizationRun::STEP_AD_ACCOUNTS)];

        foreach ($adAccounts->flatMap->metaUsers->unique('id') as $metaUser) {
            $chain[] = new SyncMetaAdAccounts($metaUser);
        }

        $chain[] = new MarkOptimizationRunStep($runId, OptimizationRun::STEP_CAMPAIGNS);
        foreach ($adAccounts as $account) {
            $chain[] = new SyncCampaigns($account);
        }

        $chain[] = new MarkOptimizationRunStep($runId, OptimizationRun::STEP_AD_SETS);
        foreach ($adAccounts as $account) {
            $chain[] = new SyncAdSets($account);
        }

        $chain[] = new MarkOptimizationRunStep($runId, OptimizationRun::STEP_ADS);
        foreach ($adAccounts as $account) {
            $chain[] = new SyncAds($account);
        }

        $chain[] = new MarkOptimizationRunStep($runId, OptimizationRun::STEP_INSIGHTS);
        foreach ($adAccounts as $account) {
            $chain[] = new SyncInsights($account, $today);
        }

        $chain[] = new MarkOptimizationRunStep($runId, OptimizationRun::STEP_EVALUATE);
        $chain[] = new EvaluateDueOptimizationRules($ruleIds, $runId);

        Bus::chain($chain)
            ->onQueue('meta-ads')
            // Any permanently-failed sync stops the chain — surface it on the run
            // so the panel shows which phase broke instead of spinning forever.
            ->catch(function (Throwable $e) use ($runId) {
                OptimizationRun::find($runId)?->markFailed($e->getMessage());
            })
            ->dispatch();
    }

    /**
     * @param  array<int, int>  $ruleIds
     */
    private function markEvaluated(array $ruleIds, Carbon $at): void
    {
        OptimizationRule::whereIn('id', $ruleIds)->update(['last_evaluated_at' => $at]);
    }
}
