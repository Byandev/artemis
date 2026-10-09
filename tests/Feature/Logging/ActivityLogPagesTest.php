<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('workspace admin can view the workspace activity log page', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    // The page is a shell: logs and summary load from the browser API (see
    // ActivityLogApiTest), so only the filter options and the initial
    // filter/query state ride along as props.
    $this->actingAs($owner)
        ->get(route('workspace.activity-logs.index', ['workspace' => $workspace, 'filter' => ['category' => 'auth'], 'per_page' => 50]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/activity-logs/index')
            ->has('options.categories')
            ->where('filters.category', 'auth')
            ->where('query.per_page', '50')
            ->missing('logs')
            ->missing('summary')
        );
});

test('non-admin workspace member is forbidden', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->get(route('workspace.activity-logs.index', $workspace))
        ->assertForbidden();
});

test('super admin can view the global activity log page', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.activity-logs.index', ['filter' => ['status' => 'failure']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-logs/index')
            ->has('options.statuses')
            ->where('filters.status', 'failure')
            ->missing('logs')
            ->missing('summary')
        );
});

test('non super admin is redirected away from the global log', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.activity-logs.index'))
        ->assertRedirect();
});
