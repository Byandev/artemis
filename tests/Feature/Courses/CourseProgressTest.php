<?php

use App\Models\User;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseEnrollment;
use Modules\Courses\Models\CourseLessonCompletion;

/** A course with two lessons across one module, plus its URLs. */
function progressCourse(): array
{
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $course = Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Trackable Course',
        'status' => 'published',
    ]);

    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $one = $module->lessons()->create(['name' => 'One', 'position' => 1]);
    $two = $module->lessons()->create(['name' => 'Two', 'position' => 2]);

    $base = "/workspaces/{$workspace->slug}/courses/{$course->id}";

    return [
        'user' => $user,
        'workspace' => $workspace,
        'course' => $course,
        'module' => $module,
        'one' => $one,
        'two' => $two,
        'base' => $base,
        'oneUrl' => "{$base}/modules/{$module->id}/lessons/{$one->id}/complete",
        'twoUrl' => "{$base}/modules/{$module->id}/lessons/{$two->id}/complete",
    ];
}

it('enrolls the user and sends them to the first lesson', function () {
    ['user' => $user, 'course' => $course, 'one' => $one, 'base' => $base] = progressCourse();

    $this->post("{$base}/start")
        ->assertRedirect()
        ->assertRedirectContains("lesson={$one->id}");

    expect(CourseEnrollment::where('course_id', $course->id)
        ->where('user_id', $user->id)->exists())->toBeTrue();
});

it('resumes rather than restarting when started again', function () {
    ['user' => $user, 'course' => $course, 'two' => $two, 'base' => $base, 'twoUrl' => $twoUrl] = progressCourse();

    $this->post("{$base}/start");
    $this->post($twoUrl);
    $started = CourseEnrollment::where('course_id', $course->id)->sole()->started_at;

    // Pressing Start again must not wipe where they got to.
    $this->post("{$base}/start")->assertRedirectContains("lesson={$two->id}");

    $enrollment = CourseEnrollment::where('course_id', $course->id)->sole();

    expect(CourseEnrollment::where('course_id', $course->id)->count())->toBe(1)
        ->and($enrollment->started_at->toIso8601String())->toBe($started->toIso8601String())
        ->and($enrollment->last_lesson_id)->toBe($two->id);
});

it('marks a lesson complete and remembers it as the resume point', function () {
    ['user' => $user, 'course' => $course, 'one' => $one, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl)->assertRedirect();

    expect(CourseLessonCompletion::where('course_lesson_id', $one->id)
        ->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(CourseEnrollment::where('course_id', $course->id)->sole()->last_lesson_id)
        ->toBe($one->id);
});

it('enrolls on first completion even without pressing start', function () {
    ['user' => $user, 'course' => $course, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl)->assertRedirect();

    expect(CourseEnrollment::where('course_id', $course->id)
        ->where('user_id', $user->id)->exists())->toBeTrue();
});

it('completing the same lesson twice does not duplicate', function () {
    ['one' => $one, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);
    $this->post($oneUrl);

    expect(CourseLessonCompletion::where('course_lesson_id', $one->id)->count())->toBe(1);
});

it('marks the course finished once every lesson is done', function () {
    ['course' => $course, 'oneUrl' => $oneUrl, 'twoUrl' => $twoUrl] = progressCourse();

    $this->post($oneUrl);
    expect(CourseEnrollment::where('course_id', $course->id)->sole()->completed_at)->toBeNull();

    $this->post($twoUrl);
    expect(CourseEnrollment::where('course_id', $course->id)->sole()->completed_at)->not->toBeNull();
});

it('un-finishes the course when a completion is undone', function () {
    ['course' => $course, 'one' => $one, 'oneUrl' => $oneUrl, 'twoUrl' => $twoUrl] = progressCourse();

    $this->post($oneUrl);
    $this->post($twoUrl);

    $this->delete($oneUrl)->assertRedirect();

    expect(CourseLessonCompletion::where('course_lesson_id', $one->id)->count())->toBe(0)
        ->and(CourseEnrollment::where('course_id', $course->id)->sole()->completed_at)->toBeNull();
});

it('reports progress on the course page', function () {
    ['base' => $base, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);

    $this->get($base)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('progress.started', true)
            ->where('progress.completed_count', 1)
            ->where('progress.total_lessons', 2)
            ->where('progress.percent', 50),
    );
});

it('keeps one learner\'s progress out of another\'s', function () {
    ['workspace' => $workspace, 'base' => $base, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);

    $other = makeMemberWithPermissions($workspace, ['View Courses']);

    $this->actingAs($other)->get($base)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('progress.started', false)
            ->where('progress.completed_count', 0),
    );
});

it('sends the completed lesson ids the outline ticks with', function () {
    ['base' => $base, 'one' => $one, 'two' => $two, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);

    // The detail page's checkboxes and per-module "1/2" both read from this.
    $this->get($base)->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('progress.completed_lesson_ids', [$one->id])
            ->where('course.modules.0.lessons.0.id', $one->id)
            ->where('course.modules.0.lessons.1.id', $two->id),
    );
});

it('can toggle a lesson complete from the course detail page', function () {
    ['base' => $base, 'one' => $one, 'oneUrl' => $oneUrl] = progressCourse();

    // Same endpoints the outline checkbox posts to.
    $this->post($oneUrl)->assertRedirect();
    $this->get($base)->assertInertia(
        fn ($page) => $page->where('progress.completed_lesson_ids', [$one->id]),
    );

    $this->delete($oneUrl)->assertRedirect();
    $this->get($base)->assertInertia(
        fn ($page) => $page->where('progress.completed_lesson_ids', []),
    );
});

it('reports workspace course stats on the index', function () {
    ['workspace' => $workspace, 'oneUrl' => $oneUrl] = progressCourse();

    Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Still a draft',
        'status' => 'draft',
    ]);

    $this->post($oneUrl);

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.total_courses', 2)
            ->where('stats.active_courses', 1)
            ->where('stats.draft_courses', 1)
            ->where('stats.total_lessons', 2)
            // One member, one of two lessons done.
            ->where('stats.avg_completion', 50),
    );
});

it('ranks members on the completion leaderboard', function () {
    ['user' => $user, 'workspace' => $workspace, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('leaderboard.0.id', $user->id)
            ->where('leaderboard.0.percent', 50),
    );
});

it('leaves the leaderboard empty until someone completes something', function () {
    ['workspace' => $workspace] = progressCourse();

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page->where('leaderboard', []),
    );
});

it('rates a course against the learners enrolled in it', function () {
    ['workspace' => $workspace, 'course' => $course, 'oneUrl' => $oneUrl] = progressCourse();

    // A second member who never enrolled must not drag the rate down.
    makeMemberWithPermissions($workspace, ['View Courses']);

    $this->post($oneUrl);

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('courses.data.0.id', $course->id)
            ->where('courses.data.0.lessons_count', 2)
            ->where('courses.data.0.enrolled_count', 1)
            // One enrolled learner, 1 of their 2 lessons done.
            ->where('courses.data.0.completion_percent', 50),
    );
});

it('rates nothing for a course nobody enrolled in', function () {
    ['workspace' => $workspace] = progressCourse();

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('courses.data.0.enrolled_count', 0)
            ->where('courses.data.0.completion_percent', 0)
            ->where('stats.avg_completion', 0),
    );
});

it('averages the completion rate over enrollments, not members', function () {
    ['workspace' => $workspace, 'oneUrl' => $oneUrl] = progressCourse();

    // Three members, one enrolled. The rate describes the one taking it.
    makeMemberWithPermissions($workspace, ['View Courses']);
    makeMemberWithPermissions($workspace, ['View Courses']);

    $this->post($oneUrl);

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('stats.enrolled_count', 1)
            ->where('stats.avg_completion', 50),
    );
});

it('lists only learners who have started the course', function () {
    ['user' => $owner, 'workspace' => $workspace, 'base' => $base, 'oneUrl' => $oneUrl] = progressCourse();

    // Attached to the workspace but has never opened this course.
    makeMemberWithPermissions($workspace, ['View Courses']);

    $this->post($oneUrl);

    $this->get($base)->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('team', 1)
            ->where('team.0.id', $owner->id)
            ->where('team.0.percent', 50),
    );
});

it('lists someone who started but has completed nothing, at 0%', function () {
    ['workspace' => $workspace, 'base' => $base] = progressCourse();

    $starter = makeMemberWithPermissions($workspace, ['View Courses']);

    // Pressing Start is what counts as having begun.
    $this->actingAs($starter)->post("{$base}/start")->assertRedirect();

    $this->get($base)->assertOk()->assertInertia(
        fn ($page) => $page
            ->has('team', 1)
            ->where('team.0.id', $starter->id)
            ->where('team.0.percent', 0),
    );
});

it('leaves the team panel empty before anyone starts', function () {
    ['base' => $base] = progressCourse();

    $this->get($base)->assertOk()->assertInertia(
        fn ($page) => $page->where('team', []),
    );
});

it('leaves the team panel empty for a course with no lessons', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $empty = Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Empty',
        'status' => 'draft',
    ]);

    // Percentages of nothing would all read 0% and say nothing useful.
    $this->get("/workspaces/{$workspace->slug}/courses/{$empty->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('team', []));
});

it('does not let a non-member start a course', function () {
    ['base' => $base] = progressCourse();

    $this->actingAs(User::factory()->create())
        ->post("{$base}/start")
        ->assertForbidden();
});

it('404s progress endpoints when the courses module is off', function () {
    ['workspace' => $workspace, 'base' => $base] = progressCourse();
    $workspace->update(['courses_module_enabled' => false]);

    $this->post("{$base}/start")->assertNotFound();
});

it('never reports a course with no lessons as finished', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $empty = Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Empty',
        'status' => 'draft',
    ]);

    $this->post("/workspaces/{$workspace->slug}/courses/{$empty->id}/start")->assertRedirect();

    expect(CourseEnrollment::where('course_id', $empty->id)->sole()->completed_at)->toBeNull();
});

it('cascades progress away with the course', function () {
    ['workspace' => $workspace, 'course' => $course, 'one' => $one, 'oneUrl' => $oneUrl] = progressCourse();

    $this->post($oneUrl);
    $this->delete("/workspaces/{$workspace->slug}/courses/{$course->id}");

    expect(CourseEnrollment::where('course_id', $course->id)->count())->toBe(0)
        ->and(CourseLessonCompletion::where('course_lesson_id', $one->id)->count())->toBe(0);
});
