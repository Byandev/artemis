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

    // The action only ever touches the live Meta API in production.
    app()->detectEnvironment(fn () => 'production');

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

it('the apply job is a no-op outside production', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    ['account' => $account, 'campaign' => $campaign, 'rule' => $rule, 'proposal' => $proposal] =
        makeApprovalScenario($workspace, 'approved');

    // Default test environment is "testing", not "production".
    Http::fake(['graph.facebook.com/*' => Http::response([], 200)]);

    (new ApplyOptimizationAction((string) Str::uuid(), $rule, $account, $campaign, [], $proposal->id))
        ->handle();

    // No call to Meta, the target is untouched, and the proposal stays approved.
    Http::assertNothingSent();
    expect($campaign->fresh()->effective_status)->toBe('ACTIVE')
        ->and($proposal->fresh()->status)->toBe('approved');
});

it('bulk approve marks all approved and queues an apply job for each', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $metaUser = MetaUser::create(['id' => 8002, 'name' => 'T', 'access_token' => 'fake']);
    $account = AdAccount::create(['id' => 330, 'name' => 'A']);
    $metaUser->adAccounts()->attach($account->id);
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Kill',
        'target_type' => 'campaign', 'action' => 'pause', 'execution_mode' => 'approval',
    ]);

    $mk = function (int $cid) use ($workspace, $rule) {
        Campaign::create(['id' => $cid, 'meta_ads_account_id' => 330, 'name' => "C{$cid}", 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE']);

        return OptimizationProposal::create([
            'workspace_id' => $workspace->id, 'meta_ads_optimization_rule_id' => $rule->id,
            'meta_ads_account_id' => 330, 'target_type' => 'campaign', 'target_id' => $cid,
            'action' => 'pause', 'conditions_snapshot' => [], 'status' => 'pending',
        ]);
    };
    $p1 = $mk(900);
    $p2 = $mk(901);

    Queue::fake();
    $this->post(route('workspaces.metaads.optimization-rules.approvals.bulk-approve', ['workspace' => $workspace]), [
        'ids' => [$p1->id, $p2->id],
    ])->assertRedirect();

    expect($p1->fresh()->status)->toBe('approved')
        ->and($p2->fresh()->status)->toBe('approved');
    Queue::assertPushed(ApplyOptimizationAction::class, 2);
});

it('bulk reject marks all rejected without queuing apply jobs', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $account = AdAccount::create(['id' => 331, 'name' => 'A']);
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id, 'name' => 'Kill',
        'target_type' => 'campaign', 'action' => 'pause', 'execution_mode' => 'approval',
    ]);
    $mk = fn (int $cid) => OptimizationProposal::create([
        'workspace_id' => $workspace->id, 'meta_ads_optimization_rule_id' => $rule->id,
        'meta_ads_account_id' => 331, 'target_type' => 'campaign', 'target_id' => $cid,
        'action' => 'pause', 'conditions_snapshot' => [], 'status' => 'pending',
    ]);
    $p1 = $mk(902);
    $p2 = $mk(903);

    Queue::fake();
    $this->post(route('workspaces.metaads.optimization-rules.approvals.bulk-reject', ['workspace' => $workspace]), [
        'ids' => [$p1->id, $p2->id],
    ])->assertRedirect();

    expect($p1->fresh()->status)->toBe('rejected')
        ->and($p2->fresh()->status)->toBe('rejected');
    Queue::assertNothingPushed();
});
