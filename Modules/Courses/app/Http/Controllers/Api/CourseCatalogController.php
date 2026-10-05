<?php

namespace Modules\Courses\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Courses\Http\Controllers\Concerns\PresentsCourses;
use Modules\Courses\Models\Course;

/**
 * What the courses page loads over XHR: the grid, the stat tiles and the
 * leaderboard, one endpoint each so they load and fail on their own. The page
 * itself is just a shell (CoursesController::index).
 *
 * Someone who can edit courses is administering them and sees the whole
 * catalogue, drafts included; everyone else sees the published courses only.
 */
class CourseCatalogController extends Controller
{
    use AuthorizesRequests;
    use PresentsCourses;

    public function courses(Request $request, Workspace $workspace): JsonResponse
    {
        $canManage = $this->authorizeViewer($request, $workspace);

        // Aggregated once for the whole page rather than per card, so the grid
        // costs a fixed handful of queries however many courses there are.
        $lessonTotals = $this->lessonTotalsByCourse($workspace);
        $lengths = $this->lengthsByCourse($workspace);
        $completions = $this->completionsByCourse($workspace);
        $enrollments = $this->enrollmentsByCourse($workspace);

        $search = trim((string) $request->string('search'));
        $categories = array_filter((array) $request->input('categories', []));

        $courses = Course::ofWorkspace($workspace)
            ->unless($canManage, fn ($q) => $q->where('status', 'published'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($categories !== [], fn ($q) => $q->whereIn('category', $categories))
            // `media` is eager loaded so the cover column doesn't fire a query
            // per row on a full page of courses.
            ->with('media')
            ->withCount(['modules'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (Course $course) use ($lessonTotals, $lengths, $completions, $enrollments) {
                $lessons = $lessonTotals[$course->id] ?? 0;
                $enrolled = $enrollments[$course->id] ?? 0;

                return [
                    ...$this->present($course),
                    'lessons_count' => $lessons,
                    'duration_seconds' => $lengths[$course->id] ?? 0,
                    'enrolled_count' => $enrolled,
                    // Averaging each enrolled learner's own percentage is the
                    // same as dividing their total completions by
                    // (enrolled x lessons).
                    'completion_percent' => $lessons > 0 && $enrolled > 0
                        ? (int) round(($completions[$course->id] ?? 0) / ($enrolled * $lessons) * 100)
                        : 0,
                ];
            });

        return response()->json($courses);
    }

    public function stats(Request $request, Workspace $workspace): JsonResponse
    {
        $canManage = $this->authorizeViewer($request, $workspace);

        $lessonTotals = $this->lessonTotalsByCourse($workspace);

        return response()->json($canManage
            ? $this->workspaceStats($workspace, $lessonTotals, $this->completionsByCourse($workspace), $this->enrollmentsByCourse($workspace))
            : $this->learnerStats($request, $workspace, $this->startedCourseIds($request, $workspace), $lessonTotals));
    }

    public function leaderboard(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeViewer($request, $workspace);

        return response()->json(
            $this->completionLeaderboard($workspace, array_sum($this->lessonTotalsByCourse($workspace))),
        );
    }

    /**
     * Courses are only reachable by members of a workspace that has the module
     * switched on. Owners bypass the permission check (they hold '*'), so the
     * module flag has to be enforced here.
     *
     * @return bool whether the viewer manages courses (sees drafts and team-wide figures)
     */
    private function authorizeViewer(Request $request, Workspace $workspace): bool
    {
        abort_unless($workspace->courses_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        $this->authorize(Permission::ViewCourses->value, $workspace);

        return $request->user()->hasPermission(Permission::EditCourses->value, $workspace);
    }

    /**
     * Lesson count per course id.
     *
     * @return array<int, int>
     */
    private function lessonTotalsByCourse(Workspace $workspace): array
    {
        return DB::table('course_lessons')
            ->join('course_modules', 'course_lessons.course_module_id', '=', 'course_modules.id')
            ->join('courses', 'course_modules.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->groupBy('course_modules.course_id')
            // Aliased rather than plucked by raw expression: pluck() resolves a
            // real column name and cannot read an unnamed aggregate.
            ->selectRaw('course_modules.course_id as course_id, count(*) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Total video length per course id, in seconds.
     *
     * @return array<int, int>
     */
    private function lengthsByCourse(Workspace $workspace): array
    {
        return DB::table('course_lessons')
            ->join('course_modules', 'course_lessons.course_module_id', '=', 'course_modules.id')
            ->join('courses', 'course_modules.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->groupBy('course_modules.course_id')
            ->selectRaw('course_modules.course_id as course_id, coalesce(sum(course_lessons.duration_seconds), 0) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * How many people have enrolled in each course. This is the denominator
     * for a completion rate: a course's rate should describe the people taking
     * it, not be diluted by everyone who never opened it.
     *
     * @return array<int, int>
     */
    private function enrollmentsByCourse(Workspace $workspace): array
    {
        return DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->groupBy('course_enrollments.course_id')
            ->selectRaw('course_enrollments.course_id as course_id, count(*) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * How many lesson completions each course has, across every member.
     *
     * @return array<int, int>
     */
    private function completionsByCourse(Workspace $workspace): array
    {
        return DB::table('course_lesson_completions')
            ->join('course_lessons', 'course_lesson_completions.course_lesson_id', '=', 'course_lessons.id')
            ->join('course_modules', 'course_lessons.course_module_id', '=', 'course_modules.id')
            ->join('courses', 'course_modules.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->groupBy('course_modules.course_id')
            ->selectRaw('course_modules.course_id as course_id, count(*) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Ids of the courses in this workspace the current user has started.
     *
     * @return array<int, int>
     */
    private function startedCourseIds(Request $request, Workspace $workspace): array
    {
        return DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->where('course_enrollments.user_id', $request->user()->getKey())
            ->pluck('course_enrollments.course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * What a learner sees instead of the team-wide figures: the catalogue open
     * to them, how much of it they have picked up, and how far through those
     * they are. A team average would say nothing about their own standing.
     *
     * @param  array<int, int>  $startedIds
     * @param  array<int, int>  $lessonTotals
     * @return array<string, mixed>
     */
    private function learnerStats(Request $request, Workspace $workspace, array $startedIds, array $lessonTotals): array
    {
        // Only lessons inside the courses they started count, so finishing
        // everything they picked up reads as 100% rather than a fraction of
        // the whole catalogue.
        $lessons = array_sum(array_intersect_key($lessonTotals, array_flip($startedIds)));

        $done = $startedIds === [] ? 0 : DB::table('course_lesson_completions')
            ->join('course_lessons', 'course_lesson_completions.course_lesson_id', '=', 'course_lessons.id')
            ->join('course_modules', 'course_lessons.course_module_id', '=', 'course_modules.id')
            ->whereIn('course_modules.course_id', $startedIds)
            ->where('course_lesson_completions.user_id', $request->user()->getKey())
            ->count();

        $published = Course::ofWorkspace($workspace)->where('status', 'published')->count();

        return [
            'can_manage' => false,
            'enrolled_count' => 0,
            // Every published course, which is what their list shows.
            'total_courses' => $published,
            'draft_courses' => 0,
            'active_courses' => $published,
            // The ones they have actually picked up.
            'my_courses' => count($startedIds),
            'total_lessons' => $lessons,
            'completed_lessons' => $done,
            'avg_completion' => 0,
            'my_completion' => $lessons > 0 ? (int) round($done / $lessons * 100) : 0,
        ];
    }

    /**
     * @param  array<int, int>  $lessonTotals
     * @param  array<int, int>  $completions
     * @param  array<int, int>  $enrollments
     * @return array<string, mixed>
     */
    private function workspaceStats(Workspace $workspace, array $lessonTotals, array $completions, array $enrollments): array
    {
        $total = Course::ofWorkspace($workspace)->count();
        $published = Course::ofWorkspace($workspace)->where('status', 'published')->count();
        $lessons = array_sum($lessonTotals);

        // One enrollment's worth of work is that course's lesson count, so the
        // denominator is the lessons every enrolled learner took on. Courses
        // nobody enrolled in contribute nothing either way, rather than
        // dragging the rate toward zero.
        $expected = 0;

        foreach ($enrollments as $courseId => $enrolled) {
            $expected += $enrolled * ($lessonTotals[$courseId] ?? 0);
        }

        $done = array_sum($completions);

        return [
            'can_manage' => true,
            'total_courses' => $total,
            'draft_courses' => $total - $published,
            'active_courses' => $published,
            'total_lessons' => $lessons,
            'enrolled_count' => array_sum($enrollments),
            'my_courses' => 0,
            'completed_lessons' => 0,
            'my_completion' => 0,
            'avg_completion' => $expected > 0 ? (int) round($done / $expected * 100) : 0,
        ];
    }

    /**
     * Members ranked by how much of the workspace's course material they have
     * finished. Members who have completed nothing are left off rather than
     * padding the board with zeroes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function completionLeaderboard(Workspace $workspace, int $totalLessons): array
    {
        if ($totalLessons === 0) {
            return [];
        }

        return DB::table('course_lesson_completions')
            ->join('course_lessons', 'course_lesson_completions.course_lesson_id', '=', 'course_lessons.id')
            ->join('course_modules', 'course_lessons.course_module_id', '=', 'course_modules.id')
            ->join('courses', 'course_modules.course_id', '=', 'courses.id')
            ->join('users', 'course_lesson_completions.user_id', '=', 'users.id')
            ->where('courses.workspace_id', $workspace->id)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('users.name')
            ->limit(10)
            ->get(['users.id', 'users.name', DB::raw('count(*) as done')])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'percent' => (int) round($row->done / $totalLessons * 100),
            ])
            ->all();
    }
}
