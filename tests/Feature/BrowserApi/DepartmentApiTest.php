<?php

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;
use App\Models\Workspace;

/**
 * The departments page renders its shell over Inertia and loads the list from
 *   GET /api/workspaces/{workspace}/departments
 */
function departmentsUrl(Workspace $workspace): string
{
    return "/api/workspaces/{$workspace->slug}/departments";
}

function departmentMember(Workspace $workspace, array $permissions): User
{
    return makeMemberWithPermissions(
        $workspace,
        array_map(fn (Permission $p) => $p->value, $permissions),
        'Departments',
    );
}

// ----- Page shell -----

test('departments page renders without embedding the department list', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Department::factory()->for($workspace)->create();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/departments")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/departments/index')
            ->has('workspace')
            ->missing('departments'));
});

test('departments page is forbidden without the view permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = departmentMember($workspace, []);

    $this->actingAs($member)
        ->get("/workspaces/{$workspace->slug}/departments")
        ->assertForbidden();
});

// ----- Index -----

test('index returns a paginated list with member counts', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $department = Department::factory()->for($workspace)->create(['name' => 'Support']);
    $member = makeWorkspaceMember($workspace);
    $workspace->assignMemberDepartment($member, $department->id);

    $this->actingAs($owner)
        ->getJson(departmentsUrl($workspace))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'workspace_id', 'name', 'code', 'description', 'is_active', 'users_count', 'created_at', 'updated_at']],
            'current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links',
        ])
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Support')
        ->assertJsonPath('data.0.users_count', 1)
        ->assertJsonPath('data.0.is_active', true);
});

test('index is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    Department::factory()->for($a)->create(['name' => 'Mine']);
    Department::factory()->for($b)->create(['name' => 'NotMine']);

    $names = collect($this->actingAs($owner)
        ->getJson(departmentsUrl($a))
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Mine']);
});

test('index search filter narrows by partial name', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Department::factory()->for($workspace)->create(['name' => 'Customer Support']);
    Department::factory()->for($workspace)->create(['name' => 'Finance']);

    $names = collect($this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?filter[search]=support')
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Customer Support']);
});

test('index is_active filter returns only matching departments', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Department::factory()->for($workspace)->create(['name' => 'Live']);
    Department::factory()->for($workspace)->inactive()->create(['name' => 'Retired']);

    $names = collect($this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?filter[is_active]=0')
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Retired']);
});

test('index sorts by name ascending and descending', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
        Department::factory()->for($workspace)->create(['name' => $name]);
    }

    $asc = collect($this->actingAs($owner)->getJson(departmentsUrl($workspace).'?sort=name')->assertOk()->json('data'))->pluck('name')->all();
    $desc = collect($this->actingAs($owner)->getJson(departmentsUrl($workspace).'?sort=-name')->assertOk()->json('data'))->pluck('name')->all();

    expect($asc)->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and($desc)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

test('index sorts by member count', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $empty = Department::factory()->for($workspace)->create(['name' => 'Empty']);
    $busy = Department::factory()->for($workspace)->create(['name' => 'Busy']);
    $workspace->assignMemberDepartment(makeWorkspaceMember($workspace), $busy->id);
    $workspace->assignMemberDepartment(makeWorkspaceMember($workspace), $busy->id);

    $names = collect($this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?sort=-users_count')
        ->assertOk()
        ->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Busy', 'Empty']);
});

test('index rejects a sort that is not allowed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?sort=workspace_id')
        ->assertStatus(400);
});

test('index honours per_page and page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Department::factory()->for($workspace)->count(5)->create();

    $this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?per_page=2&page=3')
        ->assertOk()
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('current_page', 3)
        ->assertJsonPath('last_page', 3)
        ->assertJsonCount(1, 'data');
});

test('index caps per_page at 100', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(departmentsUrl($workspace).'?per_page=5000')
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});

test('index is allowed for a member with the view permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    Department::factory()->for($workspace)->create();
    $member = departmentMember($workspace, [Permission::ViewDepartments]);

    $this->actingAs($member)
        ->getJson(departmentsUrl($workspace))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

test('index is forbidden for a member without the view permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = departmentMember($workspace, [Permission::CreateDepartments]);

    $this->actingAs($member)->getJson(departmentsUrl($workspace))->assertForbidden();
});

test('index is forbidden for a non-member', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(User::factory()->create())
        ->getJson(departmentsUrl($workspace))
        ->assertForbidden();
});

test('index requires authentication', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson(departmentsUrl($workspace))->assertUnauthorized();
});

test('index 404s for an unknown workspace', function () {
    ['user' => $owner] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson('/api/workspaces/does-not-exist/departments')
        ->assertNotFound();
});
