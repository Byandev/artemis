<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The roles page's table loads from GET /api/workspaces/{workspace}/roles.
 * The Inertia page only renders the shell and the URL query.
 */
function rolesApiUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/roles";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function rolesApiNames($response): array
{
    return collect($response->json('data'))->pluck('name')->all();
}

// ── Auth ────────────────────────────────────────────────────────────────

test('guests get 401', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson(rolesApiUrl($workspace))->assertUnauthorized();
});

test('a user outside the workspace gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs(User::factory()->create())
        ->getJson(rolesApiUrl($workspace))
        ->assertForbidden();
});

test('a member without View Roles gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeWorkspaceMember($workspace))
        ->getJson(rolesApiUrl($workspace))
        ->assertForbidden();
});

test('a member with View Roles can list roles', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Editor']);
    $viewer = makeMemberWithPermissions($workspace, [PermissionEnum::ViewRoles->value], 'Roles');

    $names = rolesApiNames($this->actingAs($viewer)
        ->getJson(rolesApiUrl($workspace))
        ->assertOk());

    expect($names)->toContain('Editor');
});

test('an unknown workspace gets 404', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/workspaces/no-such-workspace/roles')
        ->assertNotFound();
});

// ── Shape / scoping ─────────────────────────────────────────────────────

test('it answers a paginated list', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Editor', 'description' => 'Can edit']);

    $response = $this->actingAs($owner)
        ->getJson(rolesApiUrl($workspace, ['filter' => ['search' => 'Editor']]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'description', 'created_at']],
            'current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links',
        ]);

    expect($response->json('data.0.name'))->toBe('Editor')
        ->and($response->json('data.0.description'))->toBe('Can edit')
        ->and($response->json('per_page'))->toBe(10);
});

test('it only lists roles of the requested workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $a->id, 'name' => 'Mine']);
    Role::factory()->create(['workspace_id' => $b->id, 'name' => 'Theirs']);

    $names = rolesApiNames($this->actingAs($owner)->getJson(rolesApiUrl($a))->assertOk());

    expect($names)->toContain('Mine')->not->toContain('Theirs');
});

test('archived roles are left out (they have their own page)', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Active']);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Trashed'])->delete();

    $names = rolesApiNames($this->actingAs($owner)->getJson(rolesApiUrl($w))->assertOk());

    expect($names)->toContain('Active')->not->toContain('Trashed');
});

// ── Filter / sort / paginate ────────────────────────────────────────────

test('filter[search] matches role names partially', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'AdminLevel1']);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Editor']);

    $names = rolesApiNames($this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['filter' => ['search' => 'admin']]))
        ->assertOk());

    expect($names)->toBe(['AdminLevel1']);
});

test('sort=name returns ascending and sort=-name descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (['zz-Charlie', 'zz-Alpha', 'zz-Bravo'] as $name) {
        Role::factory()->create(['workspace_id' => $w->id, 'name' => $name]);
    }
    $search = ['search' => 'zz-'];

    expect(rolesApiNames($this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['filter' => $search, 'sort' => 'name']))->assertOk()))
        ->toBe(['zz-Alpha', 'zz-Bravo', 'zz-Charlie'])
        ->and(rolesApiNames($this->actingAs($owner)
            ->getJson(rolesApiUrl($w, ['filter' => $search, 'sort' => '-name']))->assertOk()))
        ->toBe(['zz-Charlie', 'zz-Bravo', 'zz-Alpha']);
});

test('it defaults to newest first', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'zz-Old', 'created_at' => now()->subDays(2)]);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'zz-New', 'created_at' => now()->addMinute()]);

    $names = rolesApiNames($this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['filter' => ['search' => 'zz-']]))
        ->assertOk());

    expect($names)->toBe(['zz-New', 'zz-Old']);
});

test('an unknown sort is rejected with 400', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['sort' => 'workspace_id']))
        ->assertStatus(400);
});

test('per_page and page paginate the list', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (['zz-A', 'zz-B', 'zz-C'] as $name) {
        Role::factory()->create(['workspace_id' => $w->id, 'name' => $name]);
    }

    $response = $this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['filter' => ['search' => 'zz-'], 'sort' => 'name', 'per_page' => 2, 'page' => 2]))
        ->assertOk();

    expect(rolesApiNames($response))->toBe(['zz-C'])
        ->and($response->json('last_page'))->toBe(2)
        ->and($response->json('total'))->toBe(3);
});

test('per_page is capped at 100', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(rolesApiUrl($w, ['per_page' => 5000]))
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});

// ── Inertia shell ───────────────────────────────────────────────────────

test('the roles page ships the query but not the table', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/roles?sort=name&page=2&filter[search]=x")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->where('query.sort', 'name')
            ->where('query.page', '2')
            ->where('query.filter.search', 'x')
            ->missing('roles'));
});
