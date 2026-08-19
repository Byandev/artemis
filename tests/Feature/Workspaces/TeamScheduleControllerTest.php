<?php

use App\Models\Team;
use App\Models\TeamMemberSchedule;

test('owner can view the team schedule page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule")
        ->assertOk();
});

test('owner can save shifts for the week', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member);

    $monday = now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule")
        ->put("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule", [
            'week_start' => $monday,
            'schedules' => [
                ['user_id' => $member->id, 'date' => $monday, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(TeamMemberSchedule::where('team_id', $team->id)->count())->toBe(1);
});

test('clearing every shift in the week saves and empties the schedule', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member);

    $monday = now()->startOfWeek(Carbon\Carbon::MONDAY);

    // Two existing shifts in the week the user is about to clear.
    TeamMemberSchedule::insert([
        [
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ],
        [
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $monday->copy()->addDays(3)->toDateString(), 'start_time' => '10:00', 'end_time' => '18:00',
        ],
    ]);

    // The grid is emptied, so the client submits an empty schedules array.
    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule")
        ->put("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule", [
            'week_start' => $monday->toDateString(),
            'schedules' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(TeamMemberSchedule::where('team_id', $team->id)->count())->toBe(0);
});

test('clearing one week leaves other weeks untouched', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member);

    $monday = now()->startOfWeek(Carbon\Carbon::MONDAY);
    $nextMonday = $monday->copy()->addWeek();

    TeamMemberSchedule::insert([
        [
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ],
        [
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $nextMonday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ],
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule", [
            'week_start' => $monday->toDateString(),
            'schedules' => [],
        ])
        ->assertRedirect();

    $remaining = TeamMemberSchedule::where('team_id', $team->id)->pluck('date');
    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->toDateString())->toBe($nextMonday->toDateString());
});

test('schedule update on a foreign-workspace team returns 403', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignTeam = Team::factory()->create(['workspace_id' => $workspaceB->id]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/teams/{$foreignTeam->id}/schedule", [
            'week_start' => now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString(),
            'schedules' => [],
        ])
        ->assertForbidden();
});

test('schedule update requires the schedules key', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule")
        ->put("/workspaces/{$workspace->slug}/teams/{$team->id}/schedule", [
            'week_start' => now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString(),
        ])
        ->assertSessionHasErrors('schedules');
});
