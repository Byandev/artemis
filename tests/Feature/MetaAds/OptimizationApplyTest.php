<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\MetaAds\Jobs\ApplyOptimizationAction;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\User as MetaUser;

function makeApprovalScenario($workspace, string $status = 'pending'): array
{
    $metaUser = MetaUser::create(['id' => 8001, 'name' => 'Token', 'access_token' => 'fake-token']);
    $account = AdAccount::create(['id' => 320, 'name' => 'Acct']);
    $metaUser->adAccounts()->attach($account->id);

    $campaign = Campaign::create([
        'id' => 900,
        'meta_ads_account_id' => 320,
        'name' => 'Camp',
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
    ]);

    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Kill',
        'target_type' => 'campaign',
        'action' => 'pause',
        'execution_mode' => 'approval',
    ]);

    $proposal = OptimizationProposal::create([
        'workspace_id' => $workspace->id,
        'meta_ads_optimization_rule_id' => $rule->id,
        'meta_ads_account_id' => 320,
        'target_type' => 'campaign',
        'target_id' => 900,
        'action' => 'pause',
        'conditions_snapshot' => [],
        'status' => $status,
    ]);

    return compact('account', 'campaign', 'rule', 'proposal');
}

it('approving a proposal marks it approved and queues the apply job', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    ['proposal' => $proposal, 'campaign' => $campaign] = makeApprovalScenario($workspace);

    Queue::fake();

    $this->post(route('workspaces.metaads.optimization-rules.approvals.approve', [
        'workspace' => $workspace, 'proposal' => $proposal,
    ]))->assertRedirect();

    expect($proposal->fresh()->status)->toBe('approved');

    Queue::assertPushed(
        ApplyOptimizationAction::class,
        fn (ApplyOptimizationAction $job) => $job->proposalId === $proposal->id
            && (int) $job->target->getKey() === 900
            && $job->rule->action === 'pause',
    );
});

it('the apply job pauses the target on Meta and marks the proposal applied', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    ['account' => $account, 'campaign' => $campaign, 'rule' => $rule, 'proposal' => $proposal] =
        makeApprovalScenario($workspace, 'approved');

    Http::fake(['graph.facebook.com/*' => Http::response([], 200)]);

    (new ApplyOptimizationAction((string) Str::uuid(), $rule, $account, $campaign, [], $proposal->id))
        ->handle();

    // Meta received the pause...
    Http::assertSent(fn ($request) => str_contains($request->url(), '900')
        && ($request['status'] ?? null) === 'PAUSED');
    // ...the local target reflects it...
    expect($campaign->fresh()->effective_status)->toBe('PAUSED');
    // ...and the proposal is now applied.
    expect($proposal->fresh()->status)->toBe('applied');
});
