<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Teams page's table loads from GET /api/workspaces/{workspace}/teams.
 * The Inertia page only renders the shell (workspace + member picker).
 */
function teamsApiUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/teams";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function teamsViewer(Workspace $workspace, array $extra = []): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewTeams->value, ...$extra],
        PermissionEnum::ViewTeams->category(),
    );
}

// ── Auth ────────────────────────────────────────────────────────────────

test('guests get 401', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson(teamsApiUrl($workspace))->assertUnauthorized();
});

test('a user outside the workspace gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Secret']);

    $this->actingAs(User::factory()->create())
        ->getJson(teamsApiUrl($workspace))
        ->assertForbidden();
});

test('a member without View Teams gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeWorkspaceMember($workspace))
        ->getJson(teamsApiUrl($workspace))
        ->assertForbidden();
});

test('an unknown workspace gets 404', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/workspaces/no-such-workspace/teams')
        ->assertNotFound();
});

// ── Shape ───────────────────────────────────────────────────────────────

test('it answers a paginated list with member counts and members', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
    $member = makeWorkspaceMember($workspace);
    $team->members()->attach([$owner->id, $member->id]);

    $response = $this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'members_count', 'members' => [['id', 'name', 'email']]]],
            'current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links',
        ]);

    expect($response->json('data.0.name'))->toBe('Alpha')
        ->and($response->json('data.0.members_count'))->toBe(2)
        ->and(collect($response->json('data.0.members'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$owner->id, $member->id])->sort()->values()->all())
        ->and($response->json('total'))->toBe(1)
        ->and($response->json('per_page'))->toBe(10);
});

test('members do not leak passwords or tokens', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id])->members()->attach($owner->id);

    $member = $this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace))
        ->assertOk()
        ->json('data.0.members.0');

    expect(array_keys($member))->not->toContain('password', 'remember_token');
});

// ── Scoping / visibility ────────────────────────────────────────────────

test('it only lists teams of the requested workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $a->id, 'name' => 'Mine']);
    Team::factory()->create(['workspace_id' => $b->id, 'name' => 'Theirs']);

    $names = collect($this->actingAs($owner)
        ->getJson(teamsApiUrl($a))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Mine']);
});

test('a scoped member only sees the teams they belong to', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $viewer = teamsViewer($workspace);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Theirs'])->members()->attach($viewer->id);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Other']);

    $names = collect($this->actingAs($viewer)
        ->getJson(teamsApiUrl($workspace))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Theirs']);
});

test('a member with View All Workspace Data sees every team', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $viewer = teamsViewer($workspace, [PermissionEnum::ViewAllWorkspaceData->value]);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Bravo']);

    $names = collect($this->actingAs($viewer)
        ->getJson(teamsApiUrl($workspace, ['sort' => 'name']))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Alpha', 'Bravo']);
});

// ── Filter / sort / paginate ────────────────────────────────────────────

test('filter[search] matches team names partially', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Sales North']);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Sales South']);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Support']);

    $names = collect($this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace, ['filter' => ['search' => 'sales'], 'sort' => 'name']))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Sales North', 'Sales South']);
});

test('it defaults to newest first', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Old', 'created_at' => now()->subDays(2)]);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'New', 'created_at' => now()]);

    $names = collect($this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['New', 'Old']);
});

test('it sorts by members_count', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $big = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Big']);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Empty']);
    $big->members()->attach([$owner->id, makeWorkspaceMember($workspace)->id]);

    $names = collect($this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace, ['sort' => '-members_count']))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Big', 'Empty']);
});

test('an unknown sort is rejected with 400', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace, ['sort' => 'discord_webhook_url']))
        ->assertStatus(400);
});

test('per_page and page paginate the list', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    foreach (['A', 'B', 'C'] as $name) {
        Team::factory()->create(['workspace_id' => $workspace->id, 'name' => $name]);
    }

    $response = $this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace, ['sort' => 'name', 'per_page' => 2, 'page' => 2]))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['C'])
        ->and($response->json('per_page'))->toBe(2)
        ->and($response->json('last_page'))->toBe(2)
        ->and($response->json('total'))->toBe(3);
});

test('per_page is capped at 100', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(teamsApiUrl($workspace, ['per_page' => 5000]))
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});

// ── Inertia shell ───────────────────────────────────────────────────────

test('the teams page ships the query but not the table', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/teams?sort=name&page=2&filter[search]=x")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/teams/index')
            ->has('workspaceMembers')
            ->where('query.sort', 'name')
            ->where('query.page', '2')
            ->where('query.filter.search', 'x')
            ->missing('teams'));
});
