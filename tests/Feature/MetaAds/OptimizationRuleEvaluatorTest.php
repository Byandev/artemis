<?php

use Illuminate\Support\Facades\Bus;
use Modules\MetaAds\Jobs\EvaluateDueOptimizationRules;
use Modules\MetaAds\Jobs\MarkOptimizationRunStep;
use Modules\MetaAds\Jobs\SyncAds;
use Modules\MetaAds\Jobs\SyncAdSets;
use Modules\MetaAds\Jobs\SyncCampaigns;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleCondition;
use Modules\MetaAds\Models\OptimizationRun;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

it('skips a decrease that the budget floor would clamp into an increase', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 301, 'name' => 'Acct']);
    Campaign::create(['id' => 400, 'meta_ads_account_id' => 301, 'name' => 'C']);

    // Below the 75 floor → a 50% decrease clamps UP to 75 (not a real decrease).
    AdSet::create(['id' => 500, 'meta_ads_account_id' => 301, 'meta_ads_campaign_id' => 400, 'name' => 'Below floor', 'daily_budget' => 65]);
    // Above the floor → 50% decrease = 100, still above 75 → a genuine decrease.
    AdSet::create(['id' => 501, 'meta_ads_account_id' => 301, 'meta_ads_campaign_id' => 400, 'name' => 'Above floor', 'daily_budget' => 200]);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Descaling Ad Sets',
        'target_type' => 'ad_set',
        'action' => 'decrease_budget',
        'adjustment_type' => 'percentage',
        'adjustment_value' => 50,
        'budget_min' => 75,
        'condition_operator' => 'and',
        'execution_mode' => 'approval',
    ]);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'budget',
        'operator' => '>',
        'value' => 1,
        'time_window' => 'today',
    ]);

    $proposals = (new OptimizationRuleEvaluator)->plan($rule->load('conditions'), $account);

    // Only the genuine decrease (200 → 100) survives; the clamped 65 → 75 is dropped.
    expect($proposals)->toHaveCount(1)
        ->and((string) $proposals[0]['target_id'])->toBe('501')
        ->and((float) $proposals[0]['new_value'])->toBe(100.0);
});

it('floors a percentage increase to the minimum increase amount', function () {
    // 10% of 500 = 50, but the rule guarantees an increase of at least 100.
    $rule = new OptimizationRule([
        'action' => 'increase_budget',
        'adjustment_type' => 'percentage',
        'adjustment_value' => 10,
        'min_adjustment_amount' => 100,
    ]);

    expect(OptimizationRuleEvaluator::computeNewBudget($rule, 500.0))->toBe(600.0);

    // When the percentage already clears the floor, the floor is a no-op:
    // 10% of 2000 = 200 (>= 100) → 2200.
    expect(OptimizationRuleEvaluator::computeNewBudget($rule, 2000.0))->toBe(2200.0);
});

it('applies the max cap after the min floor when both are set', function () {
    // Floor 100, cap 150: 10% of 500 = 50 → floored to 100 (within cap) → 600.
    // 10% of 3000 = 300 → floored stays 300, then capped to 150 → 3150.
    $rule = new OptimizationRule([
        'action' => 'increase_budget',
        'adjustment_type' => 'percentage',
        'adjustment_value' => 10,
        'min_adjustment_amount' => 100,
        'max_adjustment_amount' => 150,
    ]);

    expect(OptimizationRuleEvaluator::computeNewBudget($rule, 500.0))->toBe(600.0)
        ->and(OptimizationRuleEvaluator::computeNewBudget($rule, 3000.0))->toBe(3150.0);
});

it('evaluates each rule independently: both rules get their own run and propose for the same target', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 310, 'name' => 'Acct']);
    Campaign::create(['id' => 410, 'meta_ads_account_id' => 310, 'name' => 'C']);
    AdSet::create(['id' => 510, 'meta_ads_account_id' => 310, 'meta_ads_campaign_id' => 410, 'name' => 'Set', 'daily_budget' => 100]);

    $makeRule = function (array $attrs, array $condition) use ($workspace, $account) {
        $rule = OptimizationRule::create(array_merge([
            'workspace_id' => $workspace->id,
            'target_type' => 'ad_set',
            'condition_operator' => 'and',
            'execution_mode' => 'approval',
            'is_active' => true,
            // Always due, so the command path is deterministic regardless of hour.
            'frequency' => 'hourly',
        ], $attrs));
        $rule->adAccounts()->attach($account->id);
        OptimizationRuleCondition::create(array_merge(
            ['meta_ads_optimization_rule_id' => $rule->id, 'time_window' => 'today'],
            $condition,
        ));

        return $rule;
    };

    // Both rules match the ad set (budget 100 <= 1000).
    $kill = $makeRule(
        ['name' => 'Kill', 'action' => 'pause', 'priority' => 5],
        ['metric' => 'budget', 'operator' => '<=', 'value' => 1000],
    );
    $descale = $makeRule(
        ['name' => 'Descale', 'action' => 'decrease_budget', 'priority' => 1, 'adjustment_type' => 'percentage', 'adjustment_value' => 50],
        ['metric' => 'budget', 'operator' => '<=', 'value' => 1000],
    );

    $this->artisan('meta-ads:evaluate-optimization-rules', ['--skip-sync' => true])->assertSuccessful();

    // Rules now run independently — each owns its own evaluation, so BOTH
    // propose for ad set 510 (no cross-rule priority claiming any more).
    $proposals = OptimizationProposal::where('target_id', 510)->get();
    expect($proposals)->toHaveCount(2)
        ->and($proposals->pluck('action')->sort()->values()->all())
        ->toBe(['decrease_budget', 'pause']);

    // And each rule gets its own tracked run.
    expect(OptimizationRun::whereIn('meta_ads_optimization_rule_id', [$kill->id, $descale->id])->count())
        ->toBe(2);
});

it('evaluates running_days from the target start time (>= 3 days)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 340, 'name' => 'A']);
    Campaign::create(['id' => 420, 'meta_ads_account_id' => 340, 'name' => 'C']);
    // Running 5 days → matches; running 1 day → doesn't.
    AdSet::create(['id' => 520, 'meta_ads_account_id' => 340, 'meta_ads_campaign_id' => 420, 'name' => 'Old', 'start_time' => now()->subDays(5)]);
    AdSet::create(['id' => 521, 'meta_ads_account_id' => 340, 'meta_ads_campaign_id' => 420, 'name' => 'New', 'start_time' => now()->subDay()]);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Age gate',
        'target_type' => 'ad_set', 'action' => 'pause',
        'condition_operator' => 'and', 'execution_mode' => 'approval', 'is_active' => true,
    ]);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'running_days', 'operator' => '>=', 'value' => 3, 'time_window' => 'today',
    ]);

    $proposals = (new OptimizationRuleEvaluator)->plan($rule->load('conditions'), $account);

    expect($proposals)->toHaveCount(1)
        ->and((string) $proposals[0]['target_id'])->toBe('520');
});

it('evaluates last_modified_in_hours from the target updated_time (>= 24 hours)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 350, 'name' => 'A']);
    Campaign::create(['id' => 430, 'meta_ads_account_id' => 350, 'name' => 'C']);
    // Untouched for 30h → matches; edited 2h ago → still in cooldown.
    AdSet::create(['id' => 530, 'meta_ads_account_id' => 350, 'meta_ads_campaign_id' => 430, 'name' => 'Stale', 'updated_time' => now()->subHours(30)]);
    AdSet::create(['id' => 531, 'meta_ads_account_id' => 350, 'meta_ads_campaign_id' => 430, 'name' => 'Fresh', 'updated_time' => now()->subHours(2)]);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Cooldown',
        'target_type' => 'ad_set', 'action' => 'pause',
        'condition_operator' => 'and', 'execution_mode' => 'approval', 'is_active' => true,
    ]);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'last_modified_in_hours', 'operator' => '>=', 'value' => 24, 'time_window' => 'today',
    ]);

    $proposals = (new OptimizationRuleEvaluator)->plan($rule->load('conditions'), $account);

    expect($proposals)->toHaveCount(1)
        ->and((string) $proposals[0]['target_id'])->toBe('530');
});

it('syncs today before evaluating: chains entity + insights sync, then the evaluation last', function () {
    Bus::fake();

    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 360, 'name' => 'Acct']);
    Campaign::create(['id' => 460, 'meta_ads_account_id' => 360, 'name' => 'C']);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Refresh then check',
        'target_type' => 'campaign', 'action' => 'pause',
        'condition_operator' => 'and', 'execution_mode' => 'approval',
        'is_active' => true, 'frequency' => 'hourly',
    ]);
    $rule->adAccounts()->attach($account->id);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'spend', 'operator' => '>=', 'value' => 0, 'time_window' => 'today',
    ]);

    $this->artisan('meta-ads:evaluate-optimization-rules')->assertSuccessful();

    // A tracked run is created for THIS rule, sitting at the first phase.
    $run = OptimizationRun::where('meta_ads_optimization_rule_id', $rule->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->workspace_id)->toBe($workspace->id)
        ->and($run->status)->toBe('running')
        ->and($run->current_step)->toBe('ad_accounts')
        ->and($run->total_accounts)->toBe(1)
        ->and(collect($run->steps)->pluck('key')->all())->toBe([
            'ad_accounts', 'campaigns', 'ad_sets', 'ads', 'insights', 'evaluate',
        ]);

    // Step markers advance the run; the entity + insights sync run between them
    // and the evaluation runs LAST, on that fresh data. (No SyncMetaAdAccounts
    // here because the account has no Meta user attached.)
    Bus::assertChained([
        MarkOptimizationRunStep::class, // ad_accounts
        MarkOptimizationRunStep::class, // campaigns
        SyncCampaigns::class,
        MarkOptimizationRunStep::class, // ad_sets
        SyncAdSets::class,
        MarkOptimizationRunStep::class, // ads
        SyncAds::class,
        MarkOptimizationRunStep::class, // insights
        SyncInsights::class,
        MarkOptimizationRunStep::class, // evaluate
        EvaluateDueOptimizationRules::class,
    ]);

    // Rule is marked handled for this hour so the next tick won't re-queue it.
    expect($rule->fresh()->last_evaluated_at)->not->toBeNull();
});

it('does not sync when --skip-sync is given: evaluates immediately but still tracks a run', function () {
    Bus::fake();

    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 361, 'name' => 'Acct']);
    Campaign::create(['id' => 461, 'meta_ads_account_id' => 361, 'name' => 'C']);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Check only',
        'target_type' => 'campaign', 'action' => 'pause',
        'condition_operator' => 'and', 'execution_mode' => 'approval',
        'is_active' => true, 'frequency' => 'hourly',
    ]);
    $rule->adAccounts()->attach($account->id);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'spend', 'operator' => '>=', 'value' => 0, 'time_window' => 'today',
    ]);

    $this->artisan('meta-ads:evaluate-optimization-rules', ['--skip-sync' => true])->assertSuccessful();

    // No sync jobs queued; the evaluation runs straight on existing data.
    Bus::assertNotDispatched(SyncCampaigns::class);
    Bus::assertNotDispatched(SyncInsights::class);
    Bus::assertDispatched(EvaluateDueOptimizationRules::class);

    // Still tracked as a run for this rule, just a single-phase one.
    $run = OptimizationRun::where('meta_ads_optimization_rule_id', $rule->id)->first();
    expect($run)->not->toBeNull()
        ->and(collect($run->steps)->pluck('key')->all())->toBe(['evaluate']);
});

it('advances the run when a step marker runs', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $run = OptimizationRun::create([
        'workspace_id' => $workspace->id,
        'status' => 'running',
        'current_step' => 'ad_accounts',
        'steps' => OptimizationRun::planFor([
            'ad_accounts', 'campaigns', 'ad_sets', 'ads', 'insights', 'evaluate',
        ]),
        'total_rules' => 1,
        'total_accounts' => 1,
        'started_at' => now(),
    ]);

    (new MarkOptimizationRunStep([$run->id], 'ads'))->handle();

    expect($run->fresh()->current_step)->toBe('ads');
});

it('syncs a shared ad account only once for multiple rules, but tracks each rule', function () {
    Bus::fake();

    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    // One account, used by two different rules.
    $account = AdAccount::create(['id' => 370, 'name' => 'Shared']);
    Campaign::create(['id' => 470, 'meta_ads_account_id' => 370, 'name' => 'C']);

    $makeRule = function (string $name) use ($workspace, $account) {
        $rule = OptimizationRule::create([
            'workspace_id' => $workspace->id, 'name' => $name,
            'target_type' => 'campaign', 'action' => 'pause',
            'condition_operator' => 'and', 'execution_mode' => 'approval',
            'is_active' => true, 'frequency' => 'hourly',
        ]);
        $rule->adAccounts()->attach($account->id);
        OptimizationRuleCondition::create([
            'meta_ads_optimization_rule_id' => $rule->id,
            'metric' => 'spend', 'operator' => '>=', 'value' => 0, 'time_window' => 'today',
        ]);

        return $rule;
    };

    $makeRule('Rule A');
    $makeRule('Rule B');

    $this->artisan('meta-ads:evaluate-optimization-rules')->assertSuccessful();

    // The shared account is synced ONCE per phase (one SyncCampaigns, etc.), and
    // each rule is evaluated into its own run at the end.
    Bus::assertChained([
        MarkOptimizationRunStep::class,        // ad_accounts
        MarkOptimizationRunStep::class,        // campaigns
        SyncCampaigns::class,                  // account 370 — once
        MarkOptimizationRunStep::class,        // ad_sets
        SyncAdSets::class,
        MarkOptimizationRunStep::class,        // ads
        SyncAds::class,
        MarkOptimizationRunStep::class,        // insights
        SyncInsights::class,
        MarkOptimizationRunStep::class,        // evaluate
        EvaluateDueOptimizationRules::class,   // rule A
        EvaluateDueOptimizationRules::class,   // rule B
    ]);

    // Two independent runs — one per rule.
    expect(OptimizationRun::count())->toBe(2);
});

it('marks the run completed once the evaluation finishes', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $account = AdAccount::create(['id' => 362, 'name' => 'Acct']);
    Campaign::create(['id' => 462, 'meta_ads_account_id' => 362, 'name' => 'C']);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Check',
        'target_type' => 'campaign', 'action' => 'pause',
        'condition_operator' => 'and', 'execution_mode' => 'approval',
        'is_active' => true, 'frequency' => 'hourly',
    ]);
    $rule->adAccounts()->attach($account->id);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'spend', 'operator' => '>=', 'value' => 0, 'time_window' => 'today',
    ]);

    // Sync queue runs the dispatched evaluation inline, so the run finishes.
    $this->artisan('meta-ads:evaluate-optimization-rules', ['--skip-sync' => true])->assertSuccessful();

    $run = OptimizationRun::where('workspace_id', $workspace->id)->first();
    expect($run->status)->toBe('completed')
        ->and($run->current_step)->toBeNull()
        ->and($run->finished_at)->not->toBeNull();
});

it('keeps the current step when a run fails', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $run = OptimizationRun::create([
        'workspace_id' => $workspace->id,
        'status' => 'running',
        'current_step' => 'insights',
        'steps' => OptimizationRun::planFor(['ad_accounts', 'insights', 'evaluate']),
        'total_rules' => 1,
        'total_accounts' => 1,
        'started_at' => now(),
    ]);

    $run->markFailed('boom');

    $run->refresh();
    expect($run->status)->toBe('failed')
        ->and($run->current_step)->toBe('insights')
        ->and($run->error_message)->toBe('boom');
});
