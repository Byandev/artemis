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
});

it('shows a manager every course, draft included', function () {
    courseWithLesson($this->workspace, 'Published One');
    courseWithLesson($this->workspace, 'A Draft', 'draft');

    // The owner holds every permission.
    $this->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.can_manage', true)
            ->has('courses.data', 2),
    );
});

it('shows a learner every published course, started or not', function () {
    $started = courseWithLesson($this->workspace, 'Started');
    courseWithLesson($this->workspace, 'Never Opened');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$started->id}/start");

    // The catalogue stays browsable, or there would be no way to start one.
    $this->actingAs($user)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.can_manage', false)
            ->has('courses.data', 2),
    );
});

it('hides a draft course from a learner even once started', function () {
    $draft = courseWithLesson($this->workspace, 'Draft', 'draft');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$draft->id}/start");

    // Unpublishing a course should take it off a learner's list.
    $this->actingAs($user)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page->has('courses.data', 0),
    );
});

it('counts all published courses and only the started ones separately', function () {
    $started = courseWithLesson($this->workspace, 'Started');
    courseWithLesson($this->workspace, 'Untouched');
    courseWithLesson($this->workspace, 'Hidden Draft', 'draft');

    $user = learner($this->workspace);
    $this->actingAs($user)->post("{$this->url}/{$started->id}/start");

    $this->actingAs($user)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            // All Courses counts published only — the draft is not offered.
            ->where('stats.total_courses', 2)
            ->where('stats.active_courses', 2)
            // My Courses counts what they picked up.
            ->where('stats.my_courses', 1),
    );
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

    $this->actingAs($user)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.my_completion', 50)
            ->where('stats.completed_lessons', 1)
            ->where('stats.total_lessons', 2)
            ->where('stats.my_courses', 2),
    );
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

    $this->actingAs($user)->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.my_completion', 100)
            ->where('stats.total_lessons', 1),
    );
});

it('reports 0% for a learner who has started nothing', function () {
    courseWithLesson($this->workspace, 'Published');

    // They can still see and start it — the list is not empty, only My Courses.
    $this->actingAs(learner($this->workspace))->get($this->url)->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('courses.data', 1)
            ->where('stats.total_courses', 1)
            ->where('stats.my_courses', 0)
            ->where('stats.my_completion', 0),
    );
});
