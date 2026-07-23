<?php

use App\Models\User;
use Modules\EscTracker\Models\DailyEscRecord;

/**
 * Workspace owner plus a member who has logged some ESC records.
 *
 * Members come back ordered by name, and factory names are random — so the
 * names are pinned here to keep `members.0` / `members.1` deterministic.
 */
function escTrackerContext(): array
{
    $ctx = makeWorkspaceWithOwner();
    $ctx['user']->forceFill(['name' => 'AAA Owner'])->save();

    // The module is opt-in per workspace, so switch it on for these tests.
    $ctx['workspace']->forceFill(['esc_tracker_module_enabled' => true])->save();

    $member = makeWorkspaceMember($ctx['workspace']);
    $member->forceFill(['name' => 'ZZZ Member'])->save();

    return [$ctx['user'], $ctx['workspace'], $member];
}

test('the page lists workspace members with their streaks', function () {
    [$owner, $workspace, $member] = escTrackerContext();

    $member->forceFill(['current_streak' => 3, 'longest_streak' => 11])->save();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/esc-tracker')
            ->where('members.1.name', $member->name)
            ->where('members.1.current_streak', 3)
            ->where('members.1.longest_streak', 11)
        );
});

test('days_logged counts only records inside the requested range', function () {
    [$owner, $workspace, $member] = escTrackerContext();

    // Three inside the range, one before, one after.
    foreach (['2026-06-10', '2026-06-15', '2026-06-20'] as $date) {
        DailyEscRecord::factory()->create(['user_id' => $member->id, 'record_date' => $date]);
    }
    DailyEscRecord::factory()->create(['user_id' => $member->id, 'record_date' => '2026-05-31']);
    DailyEscRecord::factory()->create(['user_id' => $member->id, 'record_date' => '2026-07-01']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker?from=2026-06-01&to=2026-06-30")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.1.days_logged', 3)
            ->where('range.from', '2026-06-01')
            ->where('range.to', '2026-06-30')
            ->where('range.days', 30)
        );
});

test('each pillar is counted separately and only inside the range', function () {
    [$owner, $workspace, $member] = escTrackerContext();

    // Two days with meditation, one with learning, none with movement.
    DailyEscRecord::factory()->create([
        'user_id' => $member->id,
        'record_date' => '2026-06-10',
        'meditation_completed' => true,
        'learning_completed' => true,
        'movement_completed' => false,
    ]);
    DailyEscRecord::factory()->create([
        'user_id' => $member->id,
        'record_date' => '2026-06-11',
        'meditation_completed' => true,
        'learning_completed' => false,
        'movement_completed' => false,
    ]);

    // Outside the range — must not be counted for any pillar.
    DailyEscRecord::factory()->create([
        'user_id' => $member->id,
        'record_date' => '2026-07-01',
        'meditation_completed' => true,
        'learning_completed' => true,
        'movement_completed' => true,
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker?from=2026-06-01&to=2026-06-30")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.1.days_logged', 2)
            ->where('members.1.meditation_completed_count', 2)
            ->where('members.1.learning_completed_count', 1)
            ->where('members.1.movement_completed_count', 0)
        );
});

test('switching the range returns different counts and is not served stale from cache', function () {
    [$owner, $workspace, $member] = escTrackerContext();

    DailyEscRecord::factory()->create(['user_id' => $member->id, 'record_date' => '2026-06-10']);
    DailyEscRecord::factory()->create(['user_id' => $member->id, 'record_date' => '2026-07-10']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker?from=2026-06-01&to=2026-06-30")
        ->assertInertia(fn ($page) => $page->where('members.1.days_logged', 1));

    // A different range must not reuse the previous range's cached payload.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker?from=2026-06-01&to=2026-07-31")
        ->assertInertia(fn ($page) => $page->where('members.1.days_logged', 2));
});

test('the range defaults to the current month', function () {
    [$owner, $workspace] = escTrackerContext();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.from', now()->startOfMonth()->toDateString())
            ->where('range.to', now()->toDateString())
        );
});

test('an end date before the start date is rejected', function () {
    [$owner, $workspace] = escTrackerContext();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker?from=2026-06-30&to=2026-06-01")
        ->assertSessionHasErrors(['to']);
});

test('a non-member cannot view the tracker', function () {
    [$owner, $workspace] = escTrackerContext();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/workspaces/{$workspace->slug}/esc-tracker")
        ->assertForbidden();
});

test('the page 404s when the module is disabled for the workspace', function () {
    [$owner, $workspace] = escTrackerContext();

    $workspace->forceFill(['esc_tracker_module_enabled' => false])->save();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/esc-tracker")
        ->assertNotFound();
});

test('a member without the View ESC Tracker permission is denied', function () {
    [$owner, $workspace, $member] = escTrackerContext();

    // The member holds no role/permissions in this workspace, and unlike the
    // owner gets no short-circuit in User::hasPermission.
    $this->actingAs($member)
        ->get("/workspaces/{$workspace->slug}/esc-tracker")
        ->assertForbidden();
});
