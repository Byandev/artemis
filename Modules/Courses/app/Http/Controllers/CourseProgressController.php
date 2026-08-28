<?php

namespace Modules\Courses\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseEnrollment;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\CourseLessonCompletion;
use Modules\Courses\Models\CourseModule;

/**
 * A learner's own progress through a course. Gated on "View Courses" rather
 * than the edit permissions: taking a course is not editing it, and everyone
 * who can see a course should be able to work through it.
 */
class CourseProgressController extends Controller
{
    use AuthorizesRequests;

    /**
     * Start the course, or pick up an existing run. Idempotent — pressing it
     * again resumes rather than restarting, so progress is never wiped by a
     * stray click.
     */
    public function start(Request $request, Workspace $workspace, Course $course)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $enrollment = CourseEnrollment::firstOrCreate(
            ['course_id' => $course->id, 'user_id' => $request->user()->getKey()],
            ['started_at' => Carbon::now()],
        );

        $resumeAt = $enrollment->last_lesson_id
            ?? $course->lessons()->orderBy('course_modules.position')
                ->orderBy('course_lessons.position')
                ->value('course_lessons.id');

        return redirect()->route('workspaces.courses.preview', [
            $workspace,
            $course,
            'lesson' => $resumeAt,
        ]);
    }

    /**
     * Mark a lesson finished and remember it as the resume point.
     */
    public function complete(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $userId = $request->user()->getKey();

        CourseLessonCompletion::firstOrCreate(
            ['course_lesson_id' => $lesson->id, 'user_id' => $userId],
            ['completed_at' => Carbon::now()],
        );

        // Finishing a lesson without having pressed Start still counts as
        // starting the course.
        $enrollment = CourseEnrollment::firstOrCreate(
            ['course_id' => $course->id, 'user_id' => $userId],
            ['started_at' => Carbon::now()],
        );

        $enrollment->last_lesson_id = $lesson->id;
        $enrollment->completed_at = $this->allLessonsDone($course, $userId)
            ? ($enrollment->completed_at ?? Carbon::now())
            : null;
        $enrollment->save();

        return back();
    }

    /**
     * Undo a completion. The course stops counting as finished.
     */
    public function uncomplete(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $userId = $request->user()->getKey();

        CourseLessonCompletion::where('course_lesson_id', $lesson->id)
            ->where('user_id', $userId)
            ->delete();

        CourseEnrollment::where('course_id', $course->id)
            ->where('user_id', $userId)
            ->update(['completed_at' => null]);

        return back();
    }

    /**
     * Whether the learner has now finished every lesson in the course. A course
     * with no lessons is never "complete" — there is nothing to have done.
     */
    private function allLessonsDone(Course $course, int $userId): bool
    {
        $total = $course->lessons()->count();

        if ($total === 0) {
            return false;
        }

        $done = CourseLessonCompletion::where('user_id', $userId)
            ->whereIn('course_lesson_id', $course->lessons()->pluck('course_lessons.id'))
            ->count();

        return $done >= $total;
    }

    /**
     * Same chain of checks as the other course controllers: route-model binding
     * resolves each of course, module, and lesson by id alone.
     */
    private function guard(
        Request $request,
        Workspace $workspace,
        Course $course,
        ?CourseModule $module = null,
        ?CourseLesson $lesson = null,
    ): void {
        abort_unless($workspace->courses_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        abort_unless($course->workspace_id === $workspace->id, 404);

        if ($module) {
            abort_unless($module->course_id === $course->id, 404);
        }

        if ($lesson && $module) {
            abort_unless($lesson->course_module_id === $module->id, 404);
        }
    }
}
