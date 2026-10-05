<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Team;
use App\Models\TeamAdSpendGoal;
use App\Models\User;
use App\Models\Workspace;

/**
 * The Ad Spend Goals table's data endpoint. The page is a shell; the goals,
 * a page at a time and each with its status, come from here. These pin the
 * shape, the paging and ordering, team scoping, and that the endpoint sits
 * behind the same switches and permission as the page.
 */
function smGoalsWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update([
        'sales_marketing_dashboard_module_enabled' => true,
        'ad_spend_goals_module_enabled' => true,
    ]);

    return $workspace;
}

function smGoalsUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/ad-spend-goals";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function smGoal(Workspace $workspace, Team $team, string $start, string $end, float $target = 1000): TeamAdSpendGoal
{
    return TeamAdSpendGoal::create([
        'workspace_id' => $workspace->id,
        'team_id' => $team->id,
        'daily_target' => $target,
        'start_date' => $start,
        'end_date' => $end,
    ]);
}

/** A member holding only the view grant — scoped to the teams they are on. */
function smGoalsViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewAdSpendGoals->value],
        PermissionEnum::ViewAdSpendGoals->category(),
    );
}

test('it returns the goals as a paginated list', function () {
    $workspace = smGoalsWorkspace();
    $team = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
    $goal = smGoal($workspace, $team, '2026-03-01', '2026-03-31', 1500);

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'team_id', 'team_name', 'daily_target', 'start_date', 'end_date', 'status' => ['status', 'milestones', 'members']]],
            'current_page', 'last_page', 'per_page', 'total',
        ])
        ->assertJsonPath('data.0.id', $goal->id)
        ->assertJsonPath('data.0.team_name', 'Alpha')
        ->assertJsonPath('data.0.daily_target', 1500)
        ->assertJsonPath('data.0.start_date', '2026-03-01')
        ->assertJsonPath('data.0.end_date', '2026-03-31');
});

test('it lists the latest goals first and pages through the rest', function () {
    $workspace = smGoalsWorkspace();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $oldest = smGoal($workspace, $team, '2026-01-01', '2026-01-31');
    smGoal($workspace, $team, '2026-02-01', '2026-02-28');
    $newest = smGoal($workspace, $team, '2026-03-01', '2026-03-31');

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace, ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('total', 3);

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace, ['per_page' => 2, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $oldest->id);
});

test('another workspace\'s goals never appear', function () {
    $workspace = smGoalsWorkspace();
    $other = smGoalsWorkspace();
    smGoal($other, Team::factory()->create(['workspace_id' => $other->id]), '2026-03-01', '2026-03-31');

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('a scoped member sees only their own team\'s goals', function () {
    $workspace = smGoalsWorkspace();
    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    $visible = smGoal($workspace, $mine, '2026-03-01', '2026-03-31');
    smGoal($workspace, $theirs, '2026-03-01', '2026-03-31');

    $member = smGoalsViewer($workspace);
    $member->teams()->attach($mine);

    $this->actingAs($member)
        ->getJson(smGoalsUrl($workspace))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $visible->id);
});

test('a scoped member on no team sees nothing', function () {
    $workspace = smGoalsWorkspace();
    smGoal($workspace, Team::factory()->create(['workspace_id' => $workspace->id]), '2026-03-01', '2026-03-31');

    $this->actingAs(smGoalsViewer($workspace))
        ->getJson(smGoalsUrl($workspace))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('it refuses a member without the ad spend goals grant', function () {
    $workspace = smGoalsWorkspace();

    $member = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $this->actingAs($member)->getJson(smGoalsUrl($workspace))->assertForbidden();
});

test('it 404s when either module switch is off', function (string $flag) {
    $workspace = smGoalsWorkspace();
    $workspace->update([$flag => false]);

    $this->actingAs($workspace->owner)->getJson(smGoalsUrl($workspace))->assertNotFound();
})->with(['sales_marketing_dashboard_module_enabled', 'ad_spend_goals_module_enabled']);

test('it requires a signed-in user', function () {
    $this->getJson(smGoalsUrl(smGoalsWorkspace()))->assertUnauthorized();
});

test('the page itself ships only the shell', function () {
    $workspace = smGoalsWorkspace();
    Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($workspace->owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/ad-spend-goals?page=2&per_page=25")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/ad-spend-goals/index')
            ->has('teams', 1)
            ->where('canManage', true)
            ->where('filters.page', 2)
            ->where('filters.per_page', 25)
            ->missing('goals'));
});

// ─── One goal, for the detail page ───────────────────────────────────────────

test('it returns one goal with its status', function () {
    $workspace = smGoalsWorkspace();
    $team = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
    $goal = smGoal($workspace, $team, '2026-03-01', '2026-03-31', 1500);

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace)."/{$goal->id}")
        ->assertOk()
        ->assertJsonStructure([
            'id', 'team_id', 'team_name', 'daily_target', 'start_date', 'end_date',
            'status' => [
                'status', 'is_ended', 'days_remaining', 'recent_spend', 'recent_date',
                'starting_spend', 'milestones', 'members',
            ],
        ])
        ->assertJsonPath('id', $goal->id)
        ->assertJsonPath('team_name', 'Alpha')
        ->assertJsonPath('daily_target', 1500)
        ->assertJsonPath('start_date', '2026-03-01');
});

test('it refuses a goal from another workspace', function () {
    $workspace = smGoalsWorkspace();
    $other = smGoalsWorkspace();
    $foreign = smGoal($other, Team::factory()->create(['workspace_id' => $other->id]), '2026-03-01', '2026-03-31');

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace)."/{$foreign->id}")
        ->assertForbidden();
});

test('it 404s a goal that does not exist', function () {
    $workspace = smGoalsWorkspace();

    $this->actingAs($workspace->owner)
        ->getJson(smGoalsUrl($workspace).'/999999')
        ->assertNotFound();
});

test('one goal needs the ad spend goals grant', function () {
    $workspace = smGoalsWorkspace();
    $goal = smGoal($workspace, Team::factory()->create(['workspace_id' => $workspace->id]), '2026-03-01', '2026-03-31');

    $member = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $this->actingAs($member)->getJson(smGoalsUrl($workspace)."/{$goal->id}")->assertForbidden();
});

test('one goal 404s when the ad spend goals module is off', function () {
    $workspace = smGoalsWorkspace();
    $goal = smGoal($workspace, Team::factory()->create(['workspace_id' => $workspace->id]), '2026-03-01', '2026-03-31');

    $workspace->update(['ad_spend_goals_module_enabled' => false]);

    $this->actingAs($workspace->owner)->getJson(smGoalsUrl($workspace)."/{$goal->id}")->assertNotFound();
});

test('one goal requires a signed-in user', function () {
    $workspace = smGoalsWorkspace();
    $goal = smGoal($workspace, Team::factory()->create(['workspace_id' => $workspace->id]), '2026-03-01', '2026-03-31');

    $this->getJson(smGoalsUrl($workspace)."/{$goal->id}")->assertUnauthorized();
});

test('the detail page itself ships only the shell', function () {
    $workspace = smGoalsWorkspace();
    $goal = smGoal($workspace, Team::factory()->create(['workspace_id' => $workspace->id]), '2026-03-01', '2026-03-31');

    $this->actingAs($workspace->owner)
        ->get("/workspaces/{$workspace->slug}/ad-spend-goals/{$goal->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/ad-spend-goals/show')
            ->where('goalId', $goal->id)
            ->has('teams', 1)
            ->missing('goal'));
});
