<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('defaults the task management module to off for a new workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    expect($workspace->fresh()->task_management_module_enabled)->toBeFalse();
});

it('404s the tasks page when the module is off', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->get("/workspaces/{$workspace->slug}/tasks")->assertNotFound();
});

it('renders the tasks page for an owner when the module is on', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['task_management_module_enabled' => true]);

    $this->get("/workspaces/{$workspace->slug}/tasks")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/tasks/index')
            ->where('workspace.slug', $workspace->slug));
});

it('does not let a non-member reach a workspace\'s tasks', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['task_management_module_enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get("/workspaces/{$workspace->slug}/tasks")
        ->assertForbidden();
});

it('403s a member without the View Tasks permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['task_management_module_enabled' => true]);

    $this->actingAs(makeWorkspaceMember($workspace))
        ->get("/workspaces/{$workspace->slug}/tasks")
        ->assertForbidden();
});

it('lets a member with the View Tasks permission in', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['task_management_module_enabled' => true]);

    $this->actingAs(makeMemberWithPermissions($workspace, ['View Tasks'], 'Task Management'))
        ->get("/workspaces/{$workspace->slug}/tasks")
        ->assertOk();
});
