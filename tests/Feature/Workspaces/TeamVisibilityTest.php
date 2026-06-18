<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Modules\MetaAds\Models\AdAccount;

/**
 * Attach a user to the workspace with a scoped role (no "View All Workspace Data")
 * and optionally to some teams.
 *
 * @param  array<int, Team>  $teams
 */
function scopedMember(Workspace $workspace, array $teams = []): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $user->workspaces()->attach($workspace, ['role_id' => $role->id]);

    foreach ($teams as $team) {
        $user->teams()->attach($team);
    }

    return $user;
}

it('limits a scoped user to pages of the teams they belong to', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageBoth = Page::factory()->create(['workspace_id' => $workspace->id]);

    $pageA->teams()->attach($teamA);
    $pageB->teams()->attach($teamB);
    $pageBoth->teams()->attach([$teamA->id, $teamB->id]);

    $user = scopedMember($workspace, [$teamA]);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($pageA->id)
        ->toContain($pageBoth->id)
        ->not->toContain($pageB->id);
});

it('shows every page to the workspace owner', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::find($workspace->owner_id);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($team);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($owner, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($pageA->id)->toContain($pageB->id);
});

it('shows every page to a user with the View All Workspace Data permission', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $role->permissions()->attach(Permission::where('name', 'View All Workspace Data')->value('id'));
    $user->workspaces()->attach($workspace, ['role_id' => $role->id]);

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($team);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($pageA->id)->toContain($pageB->id);
});

it('shows no pages to a scoped user on no team (fail-closed)', function () {
    $workspace = Workspace::factory()->create();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $page = Page::factory()->create(['workspace_id' => $workspace->id]);
    $page->teams()->attach($team);

    $user = scopedMember($workspace);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toBeEmpty();
});

it('limits a scoped user to orders of their team pages', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);

    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($teamA);

    $orderA = Order::factory()->create(['workspace_id' => $workspace->id, 'page_id' => $pageA->id]);
    $orderB = Order::factory()->create(['workspace_id' => $workspace->id, 'page_id' => $pageB->id]);

    $user = scopedMember($workspace, [$teamA]);

    $visible = Order::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($orderA->id)->not->toContain($orderB->id);
});

it('grants ad-account management only with a manage-tier team link', function () {
    $workspace = Workspace::factory()->create();
    $teamView = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamManage = Team::factory()->create(['workspace_id' => $workspace->id]);

    $account = AdAccount::create(['id' => 100200300, 'name' => 'Test Account']);
    $account->teams()->attach($teamView->id, ['access_level' => 'view']);
    $account->teams()->attach($teamManage->id, ['access_level' => 'manage']);

    // View-tier member: can see the account, cannot manage it.
    $viewer = scopedMember($workspace, [$teamView]);
    expect(AdAccount::query()->visibleTo($viewer, $workspace)->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->toContain((string) $account->id);
    expect(TeamVisibility::canManageAdAccount($viewer, $account, $workspace))->toBeFalse();

    // Manage-tier member: can manage it.
    $manager = scopedMember($workspace, [$teamManage]);
    expect(TeamVisibility::canManageAdAccount($manager, $account, $workspace))->toBeTrue();
});

it('narrows an unrestricted manager to the active team when one is selected', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::find($workspace->owner_id);
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);
    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($teamA);
    $pageB->teams()->attach($teamB);

    session(['active_team_id' => $teamA->id]);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($owner, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($pageA->id)->not->toContain($pageB->id);
});

it('narrows a multi-team scoped user to the active team', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);
    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($teamA);
    $pageB->teams()->attach($teamB);

    $user = scopedMember($workspace, [$teamA, $teamB]);

    session(['active_team_id' => $teamA->id]);

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($pageA->id)->not->toContain($pageB->id);
});

it('ignores an active team the user does not belong to', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);
    $pageA = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id]);
    $pageA->teams()->attach($teamA);
    $pageB->teams()->attach($teamB);

    $user = scopedMember($workspace, [$teamA]); // only team A

    session(['active_team_id' => $teamB->id]); // a team they're not in

    $visible = Page::where('workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->pluck('id')
        ->all();

    // Falls back to their own teams' union (team A), ignoring the invalid choice.
    expect($visible)->toContain($pageA->id)->not->toContain($pageB->id);
});
