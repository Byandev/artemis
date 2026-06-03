<?php

use App\Models\Role;

test('owner can view roles index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Editor']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/roles")
        ->assertOk();
});

test('owner can create a role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/roles")
        ->post("/workspaces/{$workspace->slug}/roles", [
            'name' => 'Editor',
            'description' => 'Can edit',
        ])
        ->assertRedirect();

    expect(Role::where('workspace_id', $workspace->id)->where('name', 'Editor')->exists())->toBeTrue();
});

test('store rejects duplicate role name within workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Editor']);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/roles")
        ->post("/workspaces/{$workspace->slug}/roles", ['name' => 'Editor'])
        ->assertSessionHasErrors('name');
});

test('same role name allowed across different workspaces', function () {
    ['user' => $ownerA, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['user' => $ownerB, 'workspace' => $b] = makeWorkspaceWithOwner();

    Role::factory()->create(['workspace_id' => $a->id, 'name' => 'Manager']);

    $this->actingAs($ownerB)
        ->from("/workspaces/{$b->slug}/roles")
        ->post("/workspaces/{$b->slug}/roles", ['name' => 'Manager'])
        ->assertRedirect();

    expect(Role::where('workspace_id', $b->id)->where('name', 'Manager')->exists())->toBeTrue();
});

test('owner can update a role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Old']);

    $this->actingAs($owner)
        ->patch("/workspaces/{$workspace->slug}/roles/{$role->id}", [
            'name' => 'Updated',
            'description' => 'Now better',
        ])
        ->assertRedirect();

    expect($role->fresh()->name)->toBe('Updated');
});

test('destroy soft-deletes the role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/roles/{$role->id}")
        ->assertRedirect();

    expect(Role::find($role->id))->toBeNull();
    expect(Role::withTrashed()->find($role->id))->not->toBeNull();
});

test('restore brings back a soft-deleted role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $role->delete();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/roles/{$role->id}/restore")
        ->assertRedirect();

    expect(Role::find($role->id))->not->toBeNull();
});

// ----- Filter & sort coverage -----

function rolesFromInertia($response): array
{
    return collect($response->getOriginalContent()->getData()['page']['props']['roles']['data'])
        ->pluck('name')->all();
}

test('roles index filter[search] matches partial name', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'AdminLevel1']);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Editor']);

    $names = rolesFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/roles?filter[search]=admin")->assertOk()
    );
    expect($names)->toBe(['AdminLevel1']);
});

test('roles index sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Charlie']);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Alpha']);

    $names = rolesFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/roles?sort=name")->assertOk()
    );
    expect($names)->toBe(['Alpha', 'Charlie']);
});

test('roles index sort=-created_at default puts newer first', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Old']);
    sleep(1);
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'New']);

    $names = rolesFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/roles")->assertOk()
    );
    expect($names[0])->toBe('New');
});

test('roles index includes soft-deleted (withTrashed)', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Active']);
    $deleted = Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Trashed']);
    $deleted->delete();

    $names = rolesFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/roles")->assertOk()
    );
    expect($names)->toContain('Active', 'Trashed');
});
