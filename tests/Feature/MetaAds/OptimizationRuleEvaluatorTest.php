<?php

use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleCondition;
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

it('lets the highest-priority approval rule claim a target so there are no competing proposals', function () {
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
        ], $attrs));
        $rule->adAccounts()->attach($account->id);
        OptimizationRuleCondition::create(array_merge(
            ['meta_ads_optimization_rule_id' => $rule->id, 'time_window' => 'today'],
            $condition,
        ));

        return $rule;
    };

    // Both rules match the ad set (budget 100 <= 1000); pause has higher priority.
    $kill = $makeRule(
        ['name' => 'Kill', 'action' => 'pause', 'priority' => 5],
        ['metric' => 'budget', 'operator' => '<=', 'value' => 1000],
    );
    $makeRule(
        ['name' => 'Descale', 'action' => 'decrease_budget', 'priority' => 1, 'adjustment_type' => 'percentage', 'adjustment_value' => 50],
        ['metric' => 'budget', 'operator' => '<=', 'value' => 1000],
    );

    $this->artisan('meta-ads:evaluate-optimization-rules')->assertSuccessful();

    // Only the higher-priority Kill rule owns ad set 510 — no decrease proposal.
    $proposals = OptimizationProposal::where('target_id', 510)->get();
    expect($proposals)->toHaveCount(1)
        ->and($proposals[0]->action)->toBe('pause')
        ->and((int) $proposals[0]->meta_ads_optimization_rule_id)->toBe($kill->id);
});
