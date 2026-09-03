<?php

use App\Enums\DailyTrackerCadence;
use App\Enums\Permission as PermissionEnum;
use App\Models\DailyTrackerCompletion;
use App\Models\DailyTrackerItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The Daily Tracker board: what it shows, and who may tick what on it.
 *
 * The interesting seam is the cadence. A tick is filed against the period it
 * satisfies rather than the day it was made, so a weekly deliverable ticked on
 * Monday has to still read as done on Friday while a daily one does not.
 */

/** A workspace with the S&M module on, plus its owner. */
function trackerWorkspace(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return ['owner' => $owner, 'workspace' => $workspace];
}

/** A member of $workspace holding exactly $permissions and nothing else. */
function trackerMemberWith(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $permission) {
        $row = Permission::firstOrCreate(
            ['name' => $permission->value],
            ['category' => $permission->category()],
        );

        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $row->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

function trackerItem(Workspace $workspace, array $attributes = []): DailyTrackerItem
{
    return DailyTrackerItem::factory()->create([
        'workspace_id' => $workspace->id,
        ...$attributes,
    ]);
}

function toggleUrl(Workspace $workspace): string
{
    return "/workspaces/{$workspace->slug}/sales-marketing/daily-tracker/completions";
}

test('the board lists the active deliverables in position order', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $second = trackerItem($workspace, ['label' => 'Second', 'position' => 20]);
    $first = trackerItem($workspace, ['label' => 'First', 'position' => 10]);
    trackerItem($workspace, ['label' => 'Retired', 'position' => 5, 'active' => false]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/daily-tracker/index')
            ->where('items.0.id', $first->id)
            ->where('items.1.id', $second->id)
            ->count('items', 2)
        );
});

test('another workspace\'s deliverables stay off the board', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['workspace' => $other] = trackerWorkspace();

    trackerItem($workspace, ['label' => 'Mine']);
    trackerItem($other, ['label' => 'Theirs']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker")
        ->assertInertia(fn ($page) => $page
            ->count('items', 1)
            ->where('items.0.label', 'Mine')
        );
});

test('a tick made today comes back on the board', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);

    DailyTrackerCompletion::create([
        'workspace_id' => $workspace->id,
        'daily_tracker_item_id' => $item->id,
        'user_id' => $owner->id,
        'tracked_on' => CarbonImmutable::today()->toDateString(),
        'checked_by' => $owner->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker")
        ->assertInertia(fn ($page) => $page
            ->where("completions.{$owner->id}", [$item->id])
        );
});

test('a daily tick does not carry over to the next day', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $yesterday = CarbonImmutable::parse('2026-09-02');

    DailyTrackerCompletion::create([
        'workspace_id' => $workspace->id,
        'daily_tracker_item_id' => $item->id,
        'user_id' => $owner->id,
        'tracked_on' => $yesterday->toDateString(),
        'checked_by' => $owner->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker?date=2026-09-03")
        ->assertInertia(fn ($page) => $page->where("completions.{$owner->id}", []));
});

test('a weekly tick still reads as done later in the same week', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace, ['cadence' => DailyTrackerCadence::Weekly]);

    // 2026-09-03 is a Thursday; its week starts Monday 2026-08-31.
    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $owner->id,
            'date' => '2026-08-31',
            'completed' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('daily_tracker_completions', [
        'daily_tracker_item_id' => $item->id,
        'tracked_on' => '2026-08-31',
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker?date=2026-09-03")
        ->assertInertia(fn ($page) => $page->where("completions.{$owner->id}", [$item->id]));
});

test('ticking twice leaves one row', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);

    $payload = [
        'item_id' => $item->id,
        'user_id' => $owner->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'completed' => true,
    ];

    $this->actingAs($owner)->putJson(toggleUrl($workspace), $payload)->assertOk();
    $this->actingAs($owner)->putJson(toggleUrl($workspace), $payload)->assertOk();

    expect(DailyTrackerCompletion::count())->toBe(1);
});

test('unticking removes the row, and unticking again is a no-op', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);

    $payload = [
        'item_id' => $item->id,
        'user_id' => $owner->id,
        'date' => CarbonImmutable::today()->toDateString(),
    ];

    $this->actingAs($owner)->putJson(toggleUrl($workspace), [...$payload, 'completed' => true])->assertOk();
    $this->actingAs($owner)->putJson(toggleUrl($workspace), [...$payload, 'completed' => false])->assertOk();
    $this->actingAs($owner)->putJson(toggleUrl($workspace), [...$payload, 'completed' => false])->assertOk();

    expect(DailyTrackerCompletion::count())->toBe(0);
});

test('a member may tick their own row without the manage grant', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker]);
    $item = trackerItem($workspace);

    $this->actingAs($member)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $member->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('daily_tracker_completions', [
        'daily_tracker_item_id' => $item->id,
        'user_id' => $member->id,
        'checked_by' => $member->id,
    ]);
});

test('ticking somebody else\'s row needs the manage grant', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker]);
    $colleague = trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker]);
    $item = trackerItem($workspace);

    $payload = [
        'item_id' => $item->id,
        'user_id' => $colleague->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'completed' => true,
    ];

    $this->actingAs($member)->putJson(toggleUrl($workspace), $payload)->assertForbidden();

    expect(DailyTrackerCompletion::count())->toBe(0);

    $manager = trackerMemberWith($workspace, [
        PermissionEnum::ViewDailyTracker,
        PermissionEnum::ManageDailyTracker,
    ]);

    $this->actingAs($manager)->putJson(toggleUrl($workspace), $payload)->assertOk();

    $this->assertDatabaseHas('daily_tracker_completions', [
        'user_id' => $colleague->id,
        'checked_by' => $manager->id,
    ]);
});

test('a deliverable from another workspace is rejected', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['workspace' => $other] = trackerWorkspace();

    $foreign = trackerItem($other);

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $foreign->id,
            'user_id' => $owner->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_id');
});

test('the module switch gates the tick endpoint too', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $owner->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertNotFound();
});

test('an unparseable date falls back to today rather than erroring', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker?date=not-a-date")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('date', CarbonImmutable::today()->toDateString()));
});

test('someone outside the workspace is refused, and writes nothing', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $owner->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertForbidden();

    expect(DailyTrackerCompletion::count())->toBe(0);
});

test('the roster is the workspace\'s own members', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['owner' => $stranger] = trackerWorkspace();

    $colleague = trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-tracker")
        ->assertInertia(function ($page) use ($owner, $colleague, $stranger) {
            $ids = collect($page->toArray()['props']['members'])->pluck('id');

            expect($ids)->toContain($owner->id, $colleague->id)
                ->and($ids)->not->toContain($stranger->id);
        });
});
