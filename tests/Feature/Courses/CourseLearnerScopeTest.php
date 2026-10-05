<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Courses\Models\Course;

/** A member who can view courses but not edit them. */
function learner(Workspace $workspace): User
{
    return makeMemberWithPermissions($workspace, ['View Courses']);
}

/** A course with one lesson, so completion maths is easy to read. */
function courseWithLesson(Workspace $workspace, string $name, string $status = 'published'): Course
{
    $course = Course::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'status' => $status,
    ]);

    $course->modules()
        ->create(['name' => 'M', 'position' => 1])
        ->lessons()->create(['name' => 'L', 'position' => 1]);

    return $course;
}

beforeEach(function () {
    ['workspace' => $this->workspace] = actingAsWorkspaceOwner();
    $this->workspace->update(['courses_module_enabled' => true]);
    $this->url = "/workspaces/{$this->workspace->slug}/courses";
    $this->api = "/api/workspaces/{$this->workspace->slug}/courses";
});

it('shows a manager every course, draft included', function () {
    courseWithLesson($this->workspace, 'Published One');
    courseWithLesson($this->workspace, 'A Draft', 'draft');

    // The owner holds every permission.
    $this->getJson("{$this->api}/stats")->assertOk()->assertJsonPath('can_manage', true);
    $this->getJson($this->api)->assertOk()->assertJsonCount(2, 'data');
});

it('shows a learner every published course, started or not', function () {
    $started = courseWithLesson($this->workspace, 'Started');
    courseWithLesson($this->workspace, 'Never Opened');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$started->id}/start");

    // The catalogue stays browsable, or there would be no way to start one.
    $this->actingAs($user)->getJson("{$this->api}/stats")->assertOk()->assertJsonPath('can_manage', false);
    $this->actingAs($user)->getJson($this->api)->assertOk()->assertJsonCount(2, 'data');
});

it('hides a draft course from a learner even once started', function () {
    $draft = courseWithLesson($this->workspace, 'Draft', 'draft');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$draft->id}/start");

    // Unpublishing a course should take it off a learner's list.
    $this->actingAs($user)->getJson($this->api)->assertOk()->assertJsonCount(0, 'data');
});

it('counts all published courses and only the started ones separately', function () {
    $started = courseWithLesson($this->workspace, 'Started');
    courseWithLesson($this->workspace, 'Untouched');
    courseWithLesson($this->workspace, 'Hidden Draft', 'draft');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$started->id}/start");

    $this->actingAs($user)->getJson("{$this->api}/stats")->assertOk()
        // All Courses counts published only — the draft is not offered.
        ->assertJsonPath('total_courses', 2)
        ->assertJsonPath('active_courses', 2)
        // My Courses counts what they picked up.
        ->assertJsonPath('my_courses', 1);
});

it('reports a learner\'s own completion instead of the team average', function () {
    $one = courseWithLesson($this->workspace, 'One');
    $two = courseWithLesson($this->workspace, 'Two');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$one->id}/start");
    $this->actingAs($user)->post("{$this->url}/{$two->id}/start");

    // Finish the single lesson of the first course: 1 of 2 lessons started.
    $module = $one->modules()->sole();
    $lesson = $module->lessons()->sole();
    $this->actingAs($user)->post(
        "{$this->url}/{$one->id}/modules/{$module->id}/lessons/{$lesson->id}/complete",
    );

    $this->actingAs($user)->getJson("{$this->api}/stats")->assertOk()
        ->assertJsonPath('my_completion', 50)
        ->assertJsonPath('completed_lessons', 1)
        ->assertJsonPath('total_lessons', 2)
        ->assertJsonPath('my_courses', 2);
});

it('counts only started courses toward a learner\'s percentage', function () {
    $started = courseWithLesson($this->workspace, 'Started');
    // Untouched, so its lesson must not dilute the learner's figure.
    courseWithLesson($this->workspace, 'Ignored');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$started->id}/start");

    $module = $started->modules()->sole();
    $lesson = $module->lessons()->sole();
    $this->actingAs($user)->post(
        "{$this->url}/{$started->id}/modules/{$module->id}/lessons/{$lesson->id}/complete",
    );

    $this->actingAs($user)->getJson("{$this->api}/stats")->assertOk()
        ->assertJsonPath('my_completion', 100)
        ->assertJsonPath('total_lessons', 1);
});

it('reports 0% for a learner who has started nothing', function () {
    courseWithLesson($this->workspace, 'Published');

    // They can still see and start it — the list is not empty, only My Courses.
    $learner = learner($this->workspace);

    $this->actingAs($learner)->getJson($this->api)->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($learner)->getJson("{$this->api}/stats")->assertOk()
        ->assertJsonPath('total_courses', 1)
        ->assertJsonPath('my_courses', 0)
        ->assertJsonPath('my_completion', 0);
});
