<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

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

// Filter / sort / paginate for the list live in BrowserApi/RoleListApiTest —
// the page loads its table from the browser API.

// ----- Authorization -----

test('guests are redirected to login and nothing is created', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();

    $this->get("/workspaces/{$w->slug}/roles")->assertRedirect('/login');
    $this->post("/workspaces/{$w->slug}/roles", ['name' => 'Hacked'])->assertRedirect('/login');

    expect(Role::where('name', 'Hacked')->exists())->toBeFalse();
});

test('a user outside the workspace cannot view, create, edit, archive or restore roles', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Victim']);
    $trashed = Role::factory()->create(['workspace_id' => $w->id]);
    $trashed->delete();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/workspaces/{$w->slug}/roles")->assertForbidden();
    $this->actingAs($stranger)->get("/workspaces/{$w->slug}/roles/archived")->assertForbidden();
    $this->actingAs($stranger)->post("/workspaces/{$w->slug}/roles", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($stranger)->patch("/workspaces/{$w->slug}/roles/{$role->id}", ['name' => 'Pwned'])->assertForbidden();
    $this->actingAs($stranger)->delete("/workspaces/{$w->slug}/roles/{$role->id}")->assertForbidden();
    $this->actingAs($stranger)->post("/workspaces/{$w->slug}/roles/{$trashed->id}/restore")->assertForbidden();

    expect(Role::where('name', 'Hacked')->exists())->toBeFalse()
        ->and($role->fresh()->name)->toBe('Victim')
        ->and($role->fresh()->trashed())->toBeFalse()
        ->and($trashed->fresh()->trashed())->toBeTrue();
});

test('a member without role permissions gets 403', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id]);
    $member = makeWorkspaceMember($w);

    $this->actingAs($member)->get("/workspaces/{$w->slug}/roles")->assertForbidden();
    $this->actingAs($member)->post("/workspaces/{$w->slug}/roles", ['name' => 'X'])->assertForbidden();
    $this->actingAs($member)->patch("/workspaces/{$w->slug}/roles/{$role->id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($member)->delete("/workspaces/{$w->slug}/roles/{$role->id}")->assertForbidden();
});

test('each action needs its own permission', function () {
    ['workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id, 'name' => 'Old']);
    $viewer = makeMemberWithPermissions($w, [Permission::ViewRoles->value], 'Roles');
    $editor = makeMemberWithPermissions($w, [Permission::EditRoles->value], 'Roles');

    $this->actingAs($viewer)->get("/workspaces/{$w->slug}/roles")->assertOk();
    $this->actingAs($viewer)->patch("/workspaces/{$w->slug}/roles/{$role->id}", ['name' => 'X'])->assertForbidden();

    $this->actingAs($editor)->patch("/workspaces/{$w->slug}/roles/{$role->id}", ['name' => 'New'])->assertRedirect();
    $this->actingAs($editor)->delete("/workspaces/{$w->slug}/roles/{$role->id}")->assertForbidden();

    expect($role->fresh()->name)->toBe('New')
        ->and($role->fresh()->trashed())->toBeFalse();
});

test('a role from another workspace cannot be edited, archived or have its permissions changed', function () {
    ['user' => $ownerA, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    $foreign = Role::factory()->create(['workspace_id' => $b->id, 'name' => 'Theirs']);

    $this->actingAs($ownerA)->patch("/workspaces/{$a->slug}/roles/{$foreign->id}", ['name' => 'Pwned'])->assertNotFound();
    $this->actingAs($ownerA)->delete("/workspaces/{$a->slug}/roles/{$foreign->id}")->assertNotFound();
    $this->actingAs($ownerA)->get("/workspaces/{$a->slug}/roles/{$foreign->id}/permissions")->assertNotFound();
    $this->actingAs($ownerA)->put("/workspaces/{$a->slug}/roles/{$foreign->id}/permissions", ['permission_ids' => []])->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Theirs')
        ->and($foreign->fresh()->trashed())->toBeFalse();
});
