<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a workspace owned by a fresh user, plus the user attached as 'owner'.
 *
 * @return array{user: User, workspace: Workspace}
 */
function makeWorkspaceWithOwner(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    return ['user' => $user, 'workspace' => $workspace];
}

/**
 * Create a member user attached to the given workspace with the given pivot role.
 */
function makeWorkspaceMember(Workspace $workspace, string $role = 'member'): User
{
    $user = User::factory()->create();
    $workspace->users()->attach($user->id, ['role' => $role]);

    return $user;
}

/**
 * Create a member attached to the workspace through a role holding exactly the
 * given permissions. Members attached without a role hold nothing, so anything
 * gated on a permission has to be granted one.
 */
function makeMemberWithPermissions(Workspace $workspace, array $permissions, string $category = 'Courses'): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => $category]);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

/**
 * Acting as the owner of a freshly created workspace.
 *
 * @return array{user: User, workspace: Workspace}
 */
function actingAsWorkspaceOwner(): array
{
    $ctx = makeWorkspaceWithOwner();
    test()->actingAs($ctx['user']);

    return $ctx;
}

/**
 * Generate an API key for a workspace and return [model, raw_token].
 *
 * @return array{model: WorkspaceApiKey, raw: string}
 */
function makeApiKey(Workspace $workspace, ?string $name = null): array
{
    $generated = WorkspaceApiKey::generate();
    $model = WorkspaceApiKey::create([
        'workspace_id' => $workspace->id,
        'name' => $name ?? 'Test Key',
        'key' => $generated['key'],
        'key_encrypted' => $generated['key_encrypted'],
        'key_prefix' => $generated['prefix'],
    ]);

    return ['model' => $model, 'raw' => $generated['raw']];
}
