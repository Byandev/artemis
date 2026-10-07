<?php

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Browser API behind the activity log pages: the paginated table
 * (`.../activity-logs`) and the stat cards (`.../activity-logs/summary`), for
 * both the workspace-scoped and the global admin views.
 *
 * Workspace setup writes its own `data` logs (workspace.created), so tests
 * that count rows use a category setup never touches.
 */
function logFor(?Workspace $workspace, LogCategory $category, LogStatus $status = LogStatus::Success, string $action = 'thing.update', ?User $user = null): void
{
    $builder = $user
        ? Activity::build()->asUser()->user($user)
        : Activity::build()->asSystem();

    if ($workspace) {
        $builder->workspace($workspace);
    }

    $builder->category($category)->action($action)->status($status)->message("{$action} happened")->save();
}

// ── Workspace: logs ─────────────────────────────────────────────────────────

test('workspace logs endpoint returns a paginated list with the expected shape', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    logFor($workspace, LogCategory::Auth, user: $owner, action: 'login');

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['category' => 'auth'],
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id', 'log_type', 'category', 'action_type', 'user_id', 'workspace_id',
                'status', 'message', 'created_at',
                'user' => ['id', 'name', 'email'],
            ]],
            'current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links',
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.category', 'auth')
        ->assertJsonPath('data.0.action_type', 'login')
        ->assertJsonPath('data.0.user.id', $owner->id)
        ->assertJsonMissingPath('data.0.workspace');
});

test('workspace logs endpoint is scoped to the workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    logFor($workspace, LogCategory::Security, action: 'mine');
    logFor($other, LogCategory::Security, action: 'theirs');

    $response = $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['category' => 'security'],
        ]))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('action_type')->all())->toBe(['mine'])
        ->and(collect($response->json('data'))->pluck('workspace_id')->unique()->all())->toBe([$workspace->id]);
});

test('workspace logs endpoint ignores a workspace_id filter pointing elsewhere', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    logFor($other, LogCategory::Security, action: 'theirs');

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['workspace_id' => $other->id],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('workspace logs endpoint filters by status, type and search', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    logFor($workspace, LogCategory::Integration, LogStatus::Failure, 'pancake.sync');
    logFor($workspace, LogCategory::Integration, LogStatus::Success, 'pancake.sync');
    logFor($workspace, LogCategory::Integration, LogStatus::Failure, 'botcake.sync', $owner);

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['status' => 'failure', 'search' => 'pancake'],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.action_type', 'pancake.sync')
        ->assertJsonPath('data.0.status', 'failure');

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['category' => 'integration', 'log_type' => 'system'],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('workspace logs endpoint paginates and sorts', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    foreach (['first', 'second', 'third'] as $i => $action) {
        $this->travelTo(now()->addMinutes($i));
        logFor($workspace, LogCategory::Security, action: $action);
    }
    $this->travelBack();

    $base = ['workspace' => $workspace, 'filter' => ['category' => 'security'], 'per_page' => 2];

    // Default sort is newest first.
    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', $base))
        ->assertOk()
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('total', 3)
        ->assertJsonPath('last_page', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action_type', 'third');

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [...$base, 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('current_page', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action_type', 'first');

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [...$base, 'sort' => 'created_at']))
        ->assertOk()
        ->assertJsonPath('data.0.action_type', 'first');
});

test('workspace logs endpoint rejects an unknown filter or sort', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'filter' => ['ip_address' => '127.0.0.1'],
        ]))
        ->assertStatus(400);

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.index', [
            'workspace' => $workspace,
            'sort' => 'ip_address',
        ]))
        ->assertStatus(400);
});

// ── Workspace: summary ──────────────────────────────────────────────────────

test('workspace summary endpoint returns counts for the filter context', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    logFor($workspace, LogCategory::Integration, LogStatus::Success);
    logFor($workspace, LogCategory::Integration, LogStatus::Failure);
    logFor($workspace, LogCategory::Auth, LogStatus::Failure);
    logFor($other, LogCategory::Integration, LogStatus::Failure);

    // An old row counts toward the total but not the last 24 hours.
    $this->travelTo(now()->subDays(3));
    logFor($workspace, LogCategory::Integration, LogStatus::Success);
    $this->travelBack();

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.summary', [
            'workspace' => $workspace,
            'filter' => ['category' => 'integration'],
        ]))
        ->assertOk()
        ->assertExactJson(['total' => 3, 'failures' => 1, 'last_24h' => 2]);
});

test('workspace summary ignores the status facet so failures stay meaningful', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    logFor($workspace, LogCategory::Integration, LogStatus::Success);
    logFor($workspace, LogCategory::Integration, LogStatus::Failure);

    $this->actingAs($owner)
        ->getJson(route('api.workspaces.activity-logs.summary', [
            'workspace' => $workspace,
            'filter' => ['category' => 'integration', 'status' => 'success'],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('failures', 1);
});

// ── Workspace: access ───────────────────────────────────────────────────────

test('workspace endpoints allow a workspace admin', function (string $route) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $admin = User::factory()->create();
    $workspace->users()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson(route($route, $workspace))
        ->assertOk();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

test('workspace endpoints forbid a non-admin member', function (string $route) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->getJson(route($route, $workspace))
        ->assertForbidden();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

test('workspace endpoints forbid a non-member', function (string $route) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(User::factory()->create())
        ->getJson(route($route, $workspace))
        ->assertForbidden();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

test('workspace endpoints forbid an owner of a different workspace', function (string $route) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['user' => $otherOwner] = makeWorkspaceWithOwner();

    $this->actingAs($otherOwner)
        ->getJson(route($route, $workspace))
        ->assertForbidden();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

test('workspace endpoints reject guests', function (string $route) {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson(route($route, $workspace))->assertUnauthorized();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

test('workspace endpoints 404 for an unknown workspace', function (string $route) {
    ['user' => $owner] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(route($route, 'no-such-workspace'))
        ->assertNotFound();
})->with([
    'logs' => 'api.workspaces.activity-logs.index',
    'summary' => 'api.workspaces.activity-logs.summary',
]);

// ── Admin ───────────────────────────────────────────────────────────────────

test('admin logs endpoint spans every workspace and includes the workspace', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    logFor($a, LogCategory::ScheduledJob, LogStatus::Failure, 'sync.a');
    logFor($b, LogCategory::ScheduledJob, LogStatus::Failure, 'sync.b');

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.activity-logs.index', [
            'filter' => ['category' => 'scheduled_job'],
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'category', 'status', 'workspace' => ['id', 'name', 'slug']]],
            'current_page', 'last_page', 'per_page', 'total',
        ])
        ->assertJsonPath('total', 2);

    expect(collect($response->json('data'))->pluck('workspace.id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('admin logs endpoint narrows to one workspace', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    logFor($a, LogCategory::ScheduledJob, action: 'sync.a');
    logFor($b, LogCategory::ScheduledJob, action: 'sync.b');

    $this->actingAs($admin)
        ->getJson(route('api.admin.activity-logs.index', [
            'filter' => ['category' => 'scheduled_job', 'workspace_id' => $a->id],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.action_type', 'sync.a')
        ->assertJsonPath('data.0.workspace.slug', $a->slug);
});

test('admin logs endpoint includes logs with no workspace', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    logFor(null, LogCategory::System, action: 'global.task');

    $this->actingAs($admin)
        ->getJson(route('api.admin.activity-logs.index', ['filter' => ['search' => 'global.task']]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.workspace', null);
});

test('admin summary endpoint counts across workspaces', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    logFor($a, LogCategory::ScheduledJob, LogStatus::Failure);
    logFor($b, LogCategory::ScheduledJob, LogStatus::Success);

    $this->actingAs($admin)
        ->getJson(route('api.admin.activity-logs.summary', ['filter' => ['category' => 'scheduled_job']]))
        ->assertOk()
        ->assertExactJson(['total' => 2, 'failures' => 1, 'last_24h' => 2]);

    $this->actingAs($admin)
        ->getJson(route('api.admin.activity-logs.summary', [
            'filter' => ['category' => 'scheduled_job', 'workspace_id' => $b->id],
        ]))
        ->assertOk()
        ->assertExactJson(['total' => 1, 'failures' => 0, 'last_24h' => 1]);
});

test('admin endpoints forbid a non super admin, even a workspace owner', function (string $route) {
    ['user' => $owner] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(route($route))
        ->assertForbidden();
})->with([
    'logs' => 'api.admin.activity-logs.index',
    'summary' => 'api.admin.activity-logs.summary',
]);

test('admin endpoints reject guests', function (string $route) {
    $this->getJson(route($route))->assertUnauthorized();
})->with([
    'logs' => 'api.admin.activity-logs.index',
    'summary' => 'api.admin.activity-logs.summary',
]);

test('reading the logs does not itself write a log', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $before = ActivityLog::count();

    $this->actingAs($admin)->getJson(route('api.admin.activity-logs.index'))->assertOk();
    $this->actingAs($admin)->getJson(route('api.admin.activity-logs.summary'))->assertOk();

    expect(ActivityLog::count())->toBe($before);
});
