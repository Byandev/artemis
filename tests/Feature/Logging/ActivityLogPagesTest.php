<?php

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('workspace admin can view the workspace activity log', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    Activity::build()->asUser()->user($owner)->workspace($workspace)
        ->category(LogCategory::Auth)->action('login')
        ->status(LogStatus::Success)->message('Logged in')->save();

    // Filter to auth so incidental setup logs (workspace.created, etc.) don't
    // affect the assertion.
    $this->actingAs($owner)
        ->get(route('workspace.activity-logs.index', ['workspace' => $workspace, 'filter' => ['category' => 'auth']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/activity-logs/index')
            ->has('logs.data', 1)
            ->has('options.categories')
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

test('workspace log is scoped to the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();
    $other = Workspace::factory()->forOwner($owner)->create();

    Activity::build()->asUser()->user($owner)->workspace($workspace)
        ->category(LogCategory::Data)->action('thing.update')->save();
    Activity::build()->asUser()->user($owner)->workspace($other)
        ->category(LogCategory::Data)->action('thing.update')->save();

    // The page should only ever surface logs for the requested workspace.
    $expected = ActivityLog::where('workspace_id', $workspace->id)->count();

    $this->actingAs($owner)
        ->get(route('workspace.activity-logs.index', $workspace))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', $expected));

    // Sanity: the other workspace genuinely has its own (excluded) entries.
    expect(ActivityLog::where('workspace_id', $other->id)->exists())->toBeTrue();
});

test('summary stats reflect the active search/filter context', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    // Two integration logs (one a failure) + one auth log, all in-workspace.
    Activity::build()->asUser()->user($owner)->workspace($workspace)
        ->category(LogCategory::Integration)->action('sync')->status(LogStatus::Success)->save();
    Activity::build()->asUser()->user($owner)->workspace($workspace)
        ->category(LogCategory::Integration)->action('sync')->status(LogStatus::Failure)->save();
    Activity::build()->asUser()->user($owner)->workspace($workspace)
        ->category(LogCategory::Auth)->action('login')->status(LogStatus::Success)->save();

    // Filtering to integration: total counts only the 2 integration rows and
    // failures counts only the 1 integration failure (status facet excluded).
    $this->actingAs($owner)
        ->get(route('workspace.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['category' => 'integration'],
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 2)
            ->where('summary.failures', 1)
            ->where('summary.last_24h', 2)
        );
});

test('super admin can view the global activity log across workspaces', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    Activity::build()->asSystem()->workspace($workspace)
        ->category(LogCategory::ScheduledJob)->action('nightly-sync')
        ->status(LogStatus::Failure)->job('NightlySync')->save();

    $this->actingAs($admin)
        ->get(route('admin.activity-logs.index', ['filter' => ['status' => 'failure']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-logs/index')
            ->has('logs.data', 1)
            ->where('summary.failures', 1)
        );
});

test('non super admin is redirected away from the global log', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.activity-logs.index'))
        ->assertRedirect();
});
