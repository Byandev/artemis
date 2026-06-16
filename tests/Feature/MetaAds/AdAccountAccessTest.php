<?php

use App\Enums\Permission;
use App\Models\Permission as PermissionModel;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Three ad accounts (A/B/C) all visible to the workspace through one connected
 * meta user, so `AdAccount::forWorkspace()` returns them.
 *
 * @return array{A: AdAccount, B: AdAccount, C: AdAccount}
 */
function seedAdAccounts(Workspace $workspace): array
{
    $metaUser = MetaUser::create(['id' => 9001, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $a = AdAccount::create(['id' => 101, 'name' => 'Account A']);
    $b = AdAccount::create(['id' => 102, 'name' => 'Account B']);
    $c = AdAccount::create(['id' => 103, 'name' => 'Account C']);
    $metaUser->adAccounts()->attach([$a->id, $b->id, $c->id]);

    return ['A' => $a, 'B' => $b, 'C' => $c];
}

/**
 * A workspace member whose role carries exactly the given permission names.
 *
 * @param  list<string>  $permissionNames
 */
function metaMember(Workspace $workspace, array $permissionNames): User
{
    $user = User::factory()->create();
    $workspace->users()->attach($user->id, ['role' => 'member']);

    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    foreach ($permissionNames as $name) {
        $permission = PermissionModel::firstOrCreate(['name' => $name], ['category' => 'Meta Ads']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    DB::table('workspace_user')
        ->where('user_id', $user->id)
        ->where('workspace_id', $workspace->id)
        ->update(['role_id' => $role->id]);

    return $user;
}

function grantAccountAccess(Workspace $workspace, User $user, int $accountId, string $level): void
{
    DB::table('meta_ads_account_access')->insert([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'meta_ads_account_id' => $accountId,
        'access_level' => $level,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  list<int>  $accountIds
 */
function makeRule(Workspace $workspace, array $accountIds): OptimizationRule
{
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Rule '.implode('-', $accountIds),
        'target_type' => 'campaign',
        'condition_operator' => 'and',
        'action' => 'pause',
        'is_active' => true,
        'execution_mode' => 'approval',
        'priority' => 0,
        'frequency' => 'daily',
        'run_at_hour' => 9,
    ]);

    $rule->adAccounts()->sync($accountIds);
    $rule->conditions()->create(['metric' => 'spend', 'operator' => '>', 'value' => 100, 'time_window' => 'today']);

    return $rule;
}

function makeProposal(Workspace $workspace, OptimizationRule $rule, int $accountId): OptimizationProposal
{
    return OptimizationProposal::create([
        'workspace_id' => $workspace->id,
        'meta_ads_optimization_rule_id' => $rule->id,
        'meta_ads_account_id' => $accountId,
        'target_type' => 'campaign',
        // No matching campaign — applyProposal() no-ops, so no Meta API call.
        'target_id' => 999999,
        'target_name' => 'Ghost Campaign',
        'action' => 'pause',
        'conditions_snapshot' => [],
        'status' => 'pending',
    ]);
}

/**
 * @param  list<string>  $accountIds
 */
function rulePayload(array $accountIds, array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Rule',
        'meta_ads_account_ids' => $accountIds,
        'target_type' => 'campaign',
        'condition_operator' => 'and',
        'action' => 'pause',
        'is_active' => true,
        'execution_mode' => 'approval',
        'priority' => 0,
        'frequency' => 'daily',
        'run_at_hour' => 9,
        'conditions' => [
            ['metric' => 'spend', 'operator' => '>', 'value' => 100, 'time_window' => 'today'],
        ],
    ], $overrides);
}

it('shows every ad account to a member with no grants', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    seedAdAccounts($w);
    $member = metaMember($w, [Permission::ViewMetaAds->value]);

    $this->actingAs($member)
        ->get(route('workspaces.metaads.ad-accounts', $w))
        ->assertInertia(fn (Assert $page) => $page->has('adAccounts.data', 3));
});

it('restricts the ad account listing once a grant exists', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $member = metaMember($w, [Permission::ViewMetaAds->value]);
    grantAccountAccess($w, $member, $accounts['A']->id, 'view');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.ad-accounts', $w))
        ->assertInertia(fn (Assert $page) => $page->has('adAccounts.data', 1));
});

it('keeps the owner unrestricted even with a grant row', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    grantAccountAccess($w, $owner, $accounts['A']->id, 'view');

    $this->actingAs($owner)
        ->get(route('workspaces.metaads.ad-accounts', $w))
        ->assertInertia(fn (Assert $page) => $page->has('adAccounts.data', 3));
});

it('scopes the ads manager account selector to viewable accounts', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $member = metaMember($w, [Permission::ViewMetaAds->value]);
    grantAccountAccess($w, $member, $accounts['A']->id, 'view');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.ads-manager', $w))
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/index')
            ->has('accounts', 1));
});

it('scopes the optimization rules index to viewable accounts', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    makeRule($w, [$accounts['A']->id]);
    makeRule($w, [$accounts['B']->id]);

    $member = metaMember($w, [Permission::ViewOptimizationRules->value]);
    grantAccountAccess($w, $member, $accounts['A']->id, 'view');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.optimization-rules.index', $w))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rules.data', 1)
            ->where('canManageRules', false));
});

it('reports canManageRules true for a scoped manager', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    makeRule($w, [$accounts['A']->id]);
    makeRule($w, [$accounts['B']->id]);

    $member = metaMember($w, [
        Permission::ViewOptimizationRules->value,
        Permission::ManageOptimizationRules->value,
    ]);
    grantAccountAccess($w, $member, $accounts['A']->id, 'view');
    grantAccountAccess($w, $member, $accounts['B']->id, 'manage');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.optimization-rules.index', $w))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rules.data', 2)
            ->where('canManageRules', true));
});

it('lets a scoped manager create a rule only with accounts they manage', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $member = metaMember($w, [Permission::ManageOptimizationRules->value]);
    grantAccountAccess($w, $member, $accounts['B']->id, 'manage');

    $this->actingAs($member)
        ->post(route('workspaces.metaads.optimization-rules.store', $w), rulePayload([(string) $accounts['C']->id]))
        ->assertSessionHasErrors('meta_ads_account_ids.0');

    $this->actingAs($member)
        ->post(route('workspaces.metaads.optimization-rules.store', $w), rulePayload([(string) $accounts['B']->id]))
        ->assertRedirect();

    expect(OptimizationRule::where('workspace_id', $w->id)->count())->toBe(1);
});

it('lets an unscoped manager create a rule with any workspace account', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $member = metaMember($w, [Permission::ManageOptimizationRules->value]);

    $this->actingAs($member)
        ->post(route('workspaces.metaads.optimization-rules.store', $w), rulePayload([(string) $accounts['C']->id]))
        ->assertRedirect();

    expect(OptimizationRule::where('workspace_id', $w->id)->count())->toBe(1);
});

it('forbids editing a rule that includes an unmanaged account', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $rule = makeRule($w, [$accounts['A']->id]);

    $member = metaMember($w, [Permission::ManageOptimizationRules->value]);
    grantAccountAccess($w, $member, $accounts['B']->id, 'manage');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.optimization-rules.edit', ['workspace' => $w, 'optimizationRule' => $rule]))
        ->assertForbidden();

    $this->actingAs($member)
        ->put(
            route('workspaces.metaads.optimization-rules.update', ['workspace' => $w, 'optimizationRule' => $rule]),
            rulePayload([(string) $accounts['B']->id]),
        )
        ->assertForbidden();
});

it('only approves proposals for managed accounts', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $ruleA = makeRule($w, [$accounts['A']->id]);
    $ruleB = makeRule($w, [$accounts['B']->id]);
    $proposalA = makeProposal($w, $ruleA, $accounts['A']->id);
    $proposalB = makeProposal($w, $ruleB, $accounts['B']->id);

    $member = metaMember($w, [Permission::ApproveOptimizationRules->value]);
    grantAccountAccess($w, $member, $accounts['B']->id, 'manage');

    $this->actingAs($member)
        ->get(route('workspaces.metaads.optimization-rules.approvals', $w))
        ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1));

    $this->actingAs($member)
        ->post(route('workspaces.metaads.optimization-rules.approvals.approve', ['workspace' => $w, 'proposal' => $proposalA]))
        ->assertForbidden();
    expect($proposalA->fresh()->status)->toBe('pending');

    $this->actingAs($member)
        ->post(route('workspaces.metaads.optimization-rules.approvals.approve', ['workspace' => $w, 'proposal' => $proposalB]))
        ->assertRedirect();
    expect($proposalB->fresh()->status)->toBe('approved');
});

it('replaces a member\'s ad account grants', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $accounts = seedAdAccounts($w);
    $member = metaMember($w, [Permission::ViewMetaAds->value]);

    $this->actingAs($owner)->put(
        route('workspaces.members.ad-account-access.update', ['workspace' => $w->slug, 'user' => $member->id]),
        ['access' => [
            ['meta_ads_account_id' => (string) $accounts['A']->id, 'level' => 'view'],
            ['meta_ads_account_id' => (string) $accounts['B']->id, 'level' => 'manage'],
        ]],
    )->assertRedirect();

    $this->assertDatabaseHas('meta_ads_account_access', [
        'workspace_id' => $w->id,
        'user_id' => $member->id,
        'meta_ads_account_id' => $accounts['A']->id,
        'access_level' => 'view',
    ]);
    $this->assertDatabaseHas('meta_ads_account_access', [
        'workspace_id' => $w->id,
        'user_id' => $member->id,
        'meta_ads_account_id' => $accounts['B']->id,
        'access_level' => 'manage',
    ]);

    // An empty payload clears grants → member returns to full access.
    $this->actingAs($owner)->put(
        route('workspaces.members.ad-account-access.update', ['workspace' => $w->slug, 'user' => $member->id]),
        ['access' => []],
    )->assertRedirect();

    $this->assertDatabaseMissing('meta_ads_account_access', [
        'workspace_id' => $w->id,
        'user_id' => $member->id,
    ]);
});

it('rejects ad account grants for accounts outside the workspace', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    seedAdAccounts($w);
    $member = metaMember($w, [Permission::ViewMetaAds->value]);

    $this->actingAs($owner)->put(
        route('workspaces.members.ad-account-access.update', ['workspace' => $w->slug, 'user' => $member->id]),
        ['access' => [['meta_ads_account_id' => '999999', 'level' => 'view']]],
    )->assertSessionHasErrors('access.0.meta_ads_account_id');
});

it('cannot modify the owner\'s ad account access', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    seedAdAccounts($w);

    $this->actingAs($owner)->put(
        route('workspaces.members.ad-account-access.update', ['workspace' => $w->slug, 'user' => $owner->id]),
        ['access' => []],
    )->assertSessionHasErrors('error');
});
