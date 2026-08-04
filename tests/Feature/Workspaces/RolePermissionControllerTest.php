<?php

use App\Models\Permission;
use App\Models\Role;

test('owner can view role permissions edit page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions")
        ->assertOk();
});

test('owner can attach permissions to a role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $p1 = Permission::factory()->create(['category' => 'Pages']);
    $p2 = Permission::factory()->create(['category' => 'Pages']);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions", [
            'permission_ids' => [$p1->id, $p2->id],
        ])
        ->assertRedirect();

    expect($role->permissions()->count())->toBe(2);
});

test('update syncs permissions (removing previously granted)', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $p1 = Permission::factory()->create(['category' => 'Pages']);
    $p2 = Permission::factory()->create(['category' => 'Pages']);
    $role->permissions()->attach([$p1->id, $p2->id]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions", [
            'permission_ids' => [$p1->id],
        ])
        ->assertRedirect();

    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$p1->id]);
});

test('update preserves permissions in disabled categories', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['finance_module_enabled' => false]);

    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $financePerm = Permission::factory()->create(['category' => 'Finance']);
    $pagesPerm = Permission::factory()->create(['category' => 'Pages']);
    $role->permissions()->attach([$financePerm->id, $pagesPerm->id]);

    // Form submits an empty permissions array — since Finance is hidden,
    // its grants should be preserved.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions", [
            'permission_ids' => [],
        ])
        ->assertRedirect();

    $ids = $role->fresh()->permissions->pluck('id')->all();
    expect($ids)->toContain($financePerm->id)
        ->and($ids)->not->toContain($pagesPerm->id);
});

test('permissions of a disabled module are hidden from the role editor', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update([
        'creatives_module_enabled' => false,
        'rmo_module_enabled' => false,
        'leaderboard_module_enabled' => false,
    ]);
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $groups = collect(
        $this->actingAs($owner)
            ->get("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions")
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props']['groups']
    );

    // Whole category gone with the module off...
    expect($groups->pluck('category'))->not->toContain('Creatives');

    // ...and the toggles that own only part of a category hide just their own
    // permissions, leaving the rest of RTS/CSR in place.
    $names = $groups->flatMap(fn ($g) => collect($g['permissions'])->pluck('name'));
    expect($names)->not->toContain('View RMO Management')
        ->and($names)->not->toContain('Manage RMO Settings')
        ->and($names)->not->toContain('View Leaderboards')
        ->and($names)->toContain('View RTS Analytics');
});

test('update validates permission_ids is present and items exist', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions")
        ->put("/workspaces/{$workspace->slug}/roles/{$role->id}/permissions", [
            'permission_ids' => [999999],
        ])
        ->assertSessionHasErrors('permission_ids.0');
});
