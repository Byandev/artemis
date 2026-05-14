<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

test('errors when user does not exist', function () {
    $w = Workspace::factory()->create();

    $this->artisan('permissions:grant-all-view', ['user_email' => 'nope@example.test', 'workspace_slug' => $w->slug])
        ->expectsOutputToContain('User with email nope@example.test not found.')
        ->assertFailed();
});

test('errors when workspace does not exist', function () {
    $u = User::factory()->create();

    $this->artisan('permissions:grant-all-view', ['user_email' => $u->email, 'workspace_slug' => 'no-such'])
        ->expectsOutputToContain('Workspace with slug no-such not found.')
        ->assertFailed();
});

test('errors when user is not a member of the workspace', function () {
    $u = User::factory()->create();
    $w = Workspace::factory()->create();

    $this->artisan('permissions:grant-all-view', ['user_email' => $u->email, 'workspace_slug' => $w->slug])
        ->expectsOutputToContain("{$u->email} is not a member of {$w->slug}")
        ->assertFailed();
});

test('errors when no permissions exist in the system yet', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id]);
    DB::table('workspace_user')->where('user_id', $owner->id)->where('workspace_id', $w->id)->update(['role_id' => $role->id]);

    $this->artisan('permissions:grant-all-view', ['user_email' => $owner->email, 'workspace_slug' => $w->slug])
        ->expectsOutputToContain('No permissions found in the system.')
        ->assertFailed();
});

test('attaches every view permission to the member role', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id]);
    DB::table('workspace_user')->where('user_id', $owner->id)->where('workspace_id', $w->id)->update(['role_id' => $role->id]);

    Permission::create(['category' => 'Pages', 'name' => PermissionEnum::ViewPages->value]);
    Permission::create(['category' => 'Pages', 'name' => PermissionEnum::CreatePages->value]);
    Permission::create(['category' => 'Shops', 'name' => PermissionEnum::ViewShops->value]);

    $this->artisan('permissions:grant-all-view', ['user_email' => $owner->email, 'workspace_slug' => $w->slug])
        ->assertSuccessful();

    expect($role->fresh()->permissions()->count())->toBe(3);
});

test('skips permissions already granted (no duplicates)', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $role = Role::factory()->create(['workspace_id' => $w->id]);
    DB::table('workspace_user')->where('user_id', $owner->id)->where('workspace_id', $w->id)->update(['role_id' => $role->id]);

    $perm = Permission::create(['category' => 'Pages', 'name' => PermissionEnum::ViewPages->value]);
    $role->permissions()->attach($perm->id);

    $this->artisan('permissions:grant-all-view', ['user_email' => $owner->email, 'workspace_slug' => $w->slug])
        ->expectsOutputToContain('Already had: '.PermissionEnum::ViewPages->value)
        ->assertSuccessful();

    expect($role->fresh()->permissions()->count())->toBe(1);
});
