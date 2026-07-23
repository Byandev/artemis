<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// Module feature tests get the same base TestCase + RefreshDatabase as the root
// suite. Without an explicit path they'd run as plain PHPUnit tests with no
// application booted.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../Modules/EscTracker/tests/Feature');

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
