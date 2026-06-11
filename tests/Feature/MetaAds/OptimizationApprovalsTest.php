<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;

function seedApprovals($workspace): void
{
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Rule A',
        'target_type' => 'campaign',
        'action' => 'pause',
        'execution_mode' => 'approval',
    ]);

    AdAccount::create(['id' => 201, 'name' => 'Account One']);
    AdAccount::create(['id' => 202, 'name' => 'Account Two']);

    $make = function (int $account, string $action) use ($workspace, $rule) {
        OptimizationProposal::create([
            'workspace_id' => $workspace->id,
            'meta_ads_optimization_rule_id' => $rule->id,
            'meta_ads_account_id' => $account,
            'target_type' => 'campaign',
            'target_id' => 555,
            'action' => $action,
            'conditions_snapshot' => [],
            'status' => 'pending',
        ]);
    };

    $make(201, 'pause');          // P1
    $make(202, 'increase_budget'); // P2
    $make(201, 'enable');          // P3
}

function approvalsUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.optimization-rules.approvals', ['workspace' => $workspace, ...$query]);
}

it('lists pending proposals with account + action filter options', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedApprovals($workspace);

    $this->get(approvalsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/optimization-rules/approvals')
            ->has('proposals.data', 3)
            ->has('adAccounts', 2)
            // distinct pending actions offered as filter options
            ->where('actions', fn ($a) => collect($a)->sort()->values()->all() === ['enable', 'increase_budget', 'pause'])
        );
});

it('filters by multiple ad accounts', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedApprovals($workspace);

    $this->get(approvalsUrl($workspace, ['ad_account_id' => ['201']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('proposals.data', 2) // P1 + P3 on account 201
            ->where('query.accountIds', ['201'])
        );
});

it('filters by multiple actions', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedApprovals($workspace);

    $this->get(approvalsUrl($workspace, ['action' => ['pause', 'enable']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('proposals.data', 2) // pause + enable
            ->where('query.actions', ['pause', 'enable'])
        );
});

it('sums net budget impact: budget delta plus pause deducting the current budget', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Rule A',
        'target_type' => 'campaign',
        'action' => 'pause',
        'execution_mode' => 'approval',
    ]);
    AdAccount::create(['id' => 201, 'name' => 'Account One']);
    // Campaign with a 100 daily budget that a pause proposal targets.
    Campaign::create(['id' => 900, 'meta_ads_account_id' => 201, 'name' => 'C900', 'daily_budget' => 100]);

    $proposal = fn (array $attrs) => OptimizationProposal::create(array_merge([
        'workspace_id' => $workspace->id,
        'meta_ads_optimization_rule_id' => $rule->id,
        'meta_ads_account_id' => 201,
        'target_type' => 'campaign',
        'conditions_snapshot' => [],
        'status' => 'pending',
    ], $attrs));

    // pause campaign 900 → −100 (its current budget)
    $proposal(['action' => 'pause', 'target_id' => 900]);
    // increase 50 → 80 → +30
    $proposal(['action' => 'increase_budget', 'target_id' => 901, 'current_value' => 50, 'new_value' => 80]);

    $this->get(approvalsUrl($workspace))
        ->assertInertia(fn (Assert $page) => $page
            ->where('budgetImpact', fn ($v) => (float) $v === -70.0) // -100 + 30
        );
});

it('combines account and action filters', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedApprovals($workspace);

    $this->get(approvalsUrl($workspace, ['ad_account_id' => ['201'], 'action' => ['enable']]))
        ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1)); // only P3
});
