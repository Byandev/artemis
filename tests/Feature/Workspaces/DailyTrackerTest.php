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
 * The Daily Tracker board: who is on it, what it shows, and who may tick what.
 *
 * Two seams are worth pinning. Being *on* the board is its own grant — "Tracked
 * on Daily Tracker" — separate from being allowed to read it, so a lead can
 * watch without being asked for deliverables and nobody lands on the board by
 * accident. And a tick is filed against the period it satisfies rather than the
 * day it was made, so a weekly deliverable ticked on Monday still reads as done
 * on Friday while a daily one does not.
 */

/** A workspace with the S&M module on, plus its owner. */
function trackerWorkspace(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return ['owner' => $owner, 'workspace' => $workspace];
}

/** A member of $workspace holding exactly $permissions and nothing else. */
function trackerMemberWith(Workspace $workspace, array $permissions, ?string $name = null): User
{
    $user = User::factory()->create($name ? ['name' => $name] : []);

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

/** Someone the board tracks: on it, and able to read it. */
function trackedMember(Workspace $workspace, ?string $name = null): User
{
    return trackerMemberWith($workspace, [
        PermissionEnum::ViewDailyTracker,
        PermissionEnum::TrackedOnDailyTracker,
    ], $name);
}

function trackerItem(Workspace $workspace, array $attributes = []): DailyTrackerItem
{
    return DailyTrackerItem::factory()->create([
        'workspace_id' => $workspace->id,
        ...$attributes,
    ]);
}

function boardUrl(Workspace $workspace, string $query = ''): string
{
    return "/workspaces/{$workspace->slug}/sales-marketing/daily-tracker".$query;
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
        ->get(boardUrl($workspace))
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
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->count('items', 1)
            ->where('items.0.label', 'Mine')
        );
});

test('the roster is exactly the tracked members, in name order', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['workspace' => $other] = trackerWorkspace();

    $zoe = trackedMember($workspace, 'Zoe Reyes');
    $ana = trackedMember($workspace, 'Ana Cruz');

    // On the workspace but not on the board: a lead who may only read it.
    trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker], 'Bea Lead');
    // Tracked, but in a different workspace.
    trackedMember($other, 'Someone Else');

    $this->actingAs($owner)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->count('members', 2)
            ->where('members.0.id', $ana->id)
            ->where('members.1.id', $zoe->id)
        );
});

test('the owner is on the board only if a role puts them there', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $this->actingAs($owner)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page->count('members', 0));

    $tracked = trackedMember($workspace);

    $this->actingAs($owner)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->count('members', 1)
            ->where('members.0.id', $tracked->id)
        );
});

test('losing the grant takes the row off the board without erasing its ticks', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $member = trackedMember($workspace);

    $this->actingAs($member)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $member->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertOk();

    // Drop the grant from their role.
    $trackedPermissionId = Permission::where('name', PermissionEnum::TrackedOnDailyTracker->value)->value('id');
    DB::table('role_permissions')->where('permission_id', $trackedPermissionId)->delete();

    $this->actingAs($owner)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page->count('members', 0));

    expect(DailyTrackerCompletion::count())->toBe(1);
});

test('a member sees their own row flagged', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $mine = trackedMember($workspace, 'A Mine');
    trackedMember($workspace, 'B Theirs');

    $this->actingAs($mine)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->where('members.0.is_self', true)
            ->where('members.1.is_self', false)
        );
});

test('a tick made today comes back on the board', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $member = trackedMember($workspace);

    DailyTrackerCompletion::create([
        'workspace_id' => $workspace->id,
        'daily_tracker_item_id' => $item->id,
        'user_id' => $member->id,
        'tracked_on' => CarbonImmutable::today()->toDateString(),
        'checked_by' => $member->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(boardUrl($workspace))
        ->assertInertia(fn ($page) => $page->where("completions.{$member->id}", [$item->id]));
});

test('a daily tick does not carry over to the next day', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace);
    $member = trackedMember($workspace);

    DailyTrackerCompletion::create([
        'workspace_id' => $workspace->id,
        'daily_tracker_item_id' => $item->id,
        'user_id' => $member->id,
        'tracked_on' => '2026-09-02',
        'checked_by' => $member->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(boardUrl($workspace, '?date=2026-09-03'))
        ->assertInertia(fn ($page) => $page->where("completions.{$member->id}", []));
});

test('a weekly tick still reads as done later in the same week', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $item = trackerItem($workspace, ['cadence' => DailyTrackerCadence::Weekly]);
    $member = trackedMember($workspace);

    // 2026-09-03 is a Thursday; its week starts Monday 2026-08-31.
    $this->actingAs($member)
        ->putJson(toggleUrl($workspace), [
            'item_id' => $item->id,
            'user_id' => $member->id,
            'date' => '2026-08-31',
            'completed' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('daily_tracker_completions', [
        'daily_tracker_item_id' => $item->id,
        'tracked_on' => '2026-08-31',
    ]);

    $this->actingAs($owner)
        ->get(boardUrl($workspace, '?date=2026-09-03'))
        ->assertInertia(fn ($page) => $page->where("completions.{$member->id}", [$item->id]));
});

test('ticking twice leaves one row', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackedMember($workspace);
    $payload = [
        'item_id' => trackerItem($workspace)->id,
        'user_id' => $member->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'completed' => true,
    ];

    $this->actingAs($member)->putJson(toggleUrl($workspace), $payload)->assertOk();
    $this->actingAs($member)->putJson(toggleUrl($workspace), $payload)->assertOk();

    expect(DailyTrackerCompletion::count())->toBe(1);
});

test('unticking removes the row, and unticking again is a no-op', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackedMember($workspace);
    $payload = [
        'item_id' => trackerItem($workspace)->id,
        'user_id' => $member->id,
        'date' => CarbonImmutable::today()->toDateString(),
    ];

    $this->actingAs($member)->putJson(toggleUrl($workspace), [...$payload, 'completed' => true])->assertOk();
    $this->actingAs($member)->putJson(toggleUrl($workspace), [...$payload, 'completed' => false])->assertOk();
    $this->actingAs($member)->putJson(toggleUrl($workspace), [...$payload, 'completed' => false])->assertOk();

    expect(DailyTrackerCompletion::count())->toBe(0);
});

test('ticking somebody else\'s row needs the manage grant', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackedMember($workspace);
    $colleague = trackedMember($workspace);

    $payload = [
        'item_id' => trackerItem($workspace)->id,
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

test('a member who is not on the board cannot be ticked', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    // Reads the board, but has no row on it.
    $lead = trackerMemberWith($workspace, [PermissionEnum::ViewDailyTracker]);

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => trackerItem($workspace)->id,
            'user_id' => $lead->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertNotFound();

    expect(DailyTrackerCompletion::count())->toBe(0);
});

test('a member of another workspace is rejected', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['workspace' => $other] = trackerWorkspace();

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => trackerItem($workspace)->id,
            'user_id' => trackedMember($other)->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

test('a deliverable from another workspace is rejected', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();
    ['workspace' => $other] = trackerWorkspace();

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), [
            'item_id' => trackerItem($other)->id,
            'user_id' => trackedMember($workspace)->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_id');
});

test('the module switch gates the tick endpoint too', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $payload = [
        'item_id' => trackerItem($workspace)->id,
        'user_id' => trackedMember($workspace)->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'completed' => true,
    ];

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($owner)
        ->putJson(toggleUrl($workspace), $payload)
        ->assertNotFound();
});

test('someone outside the workspace is refused, and writes nothing', function () {
    ['workspace' => $workspace] = trackerWorkspace();

    $member = trackedMember($workspace);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(boardUrl($workspace))->assertForbidden();

    $this->actingAs($outsider)
        ->putJson(toggleUrl($workspace), [
            'item_id' => trackerItem($workspace)->id,
            'user_id' => $member->id,
            'date' => CarbonImmutable::today()->toDateString(),
            'completed' => true,
        ])
        ->assertForbidden();

    expect(DailyTrackerCompletion::count())->toBe(0);
});

test('an unparseable date falls back to today rather than erroring', function () {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $this->actingAs($owner)
        ->get(boardUrl($workspace, '?date=not-a-date'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('date', CarbonImmutable::today()->toDateString()));
});

test('the view comes back from the URL, and an unknown one falls back', function (string $query, string $expected) {
    ['owner' => $owner, 'workspace' => $workspace] = trackerWorkspace();

    $this->actingAs($owner)
        ->get(boardUrl($workspace, $query))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('view', $expected));
})->with([
    'no view given' => ['', 'checklist'],
    'the checklist' => ['?view=checklist', 'checklist'],
    'the matrix' => ['?view=matrix', 'matrix'],
    'something else' => ['?view=nonsense', 'checklist'],
]);
