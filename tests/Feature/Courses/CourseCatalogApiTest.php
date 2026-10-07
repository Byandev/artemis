<?php

use Modules\Courses\Models\Course;

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = actingAsWorkspaceOwner();
    $this->workspace->update(['courses_module_enabled' => true]);
    $this->api = "/api/workspaces/{$this->workspace->slug}/courses";

    $course = Course::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Onboarding',
        'category' => 'Sales',
        'status' => 'published',
    ]);

    $course->modules()
        ->create(['name' => 'M', 'position' => 1])
        ->lessons()->create(['name' => 'L', 'position' => 1]);
});

it('returns the course grid as a paginated list', function () {
    $this->getJson($this->api)->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id', 'name', 'description', 'category', 'status', 'cover_image',
                'modules_count', 'lessons_count', 'duration_seconds',
                'enrolled_count', 'completion_percent',
            ]],
            'current_page', 'last_page', 'from', 'to', 'total',
        ])
        ->assertJsonPath('data.0.modules_count', 1)
        ->assertJsonPath('data.0.lessons_count', 1);
});

it('returns the stat tiles', function () {
    $this->getJson("{$this->api}/stats")->assertOk()
        ->assertJsonStructure([
            'can_manage', 'total_courses', 'draft_courses', 'active_courses',
            'total_lessons', 'my_courses', 'completed_lessons', 'enrolled_count',
            'avg_completion', 'my_completion',
        ])
        ->assertJsonPath('total_courses', 1)
        ->assertJsonPath('total_lessons', 1);
});

it('returns the leaderboard rows', function () {
    $course = Course::sole();
    $module = $course->modules()->sole();
    $lesson = $module->lessons()->sole();

    $this->post("/workspaces/{$this->workspace->slug}/courses/{$course->id}/start");
    $this->post("/workspaces/{$this->workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/complete");

    $this->getJson("{$this->api}/leaderboard")->assertOk()
        ->assertJsonStructure([['id', 'name', 'percent']])
        ->assertJsonPath('0.id', $this->owner->id)
        ->assertJsonPath('0.percent', 100);
});

it('turns away a member without the View Courses permission', function (string $endpoint) {
    $member = makeMemberWithPermissions($this->workspace, []);

    $this->actingAs($member)->getJson("{$this->api}{$endpoint}")->assertForbidden();
})->with(['', '/stats', '/leaderboard']);

it('requires a signed-in user', function (string $endpoint) {
    auth()->logout();

    $this->getJson("{$this->api}{$endpoint}")->assertUnauthorized();
})->with(['', '/stats', '/leaderboard']);
