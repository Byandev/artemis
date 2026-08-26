<?php

namespace Modules\Courses\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseEnrollment;
use Modules\Courses\Models\CourseLessonCompletion;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class CoursesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        // Someone who can edit courses is administering them and sees the whole
        // catalogue, drafts included; everyone else sees the published courses
        // only — all of them, so they can still find something to start.
        $canManage = $request->user()->hasPermission(Permission::EditCourses->value, $workspace);

        // Aggregated once for the whole page rather than per card, so the grid
        // costs a fixed handful of queries however many courses there are.
        $lessonTotals = $this->lessonTotalsByCourse($workspace);
        $lengths = $this->lengthsByCourse($workspace);
        $completions = $this->completionsByCourse($workspace);
        $memberCount = max(1, $workspace->users()->count());

        $startedIds = $this->startedCourseIds($request, $workspace);

        $courses = Course::ofWorkspace($workspace)
            ->unless($canManage, fn ($q) => $q->where('status', 'published'))
            // `media` is eager loaded so the cover column doesn't fire a query
            // per row on a full page of courses.
            ->with('media')
            ->withCount(['modules'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (Course $course) use ($lessonTotals, $lengths, $completions, $memberCount) {
                $lessons = $lessonTotals[$course->id] ?? 0;

                return [
                    ...$this->present($course),
                    'lessons_count' => $lessons,
                    'duration_seconds' => $lengths[$course->id] ?? 0,
                    // Averaging each member's own percentage is the same as
                    // dividing total completions by (members x lessons).
                    'team_percent' => $lessons > 0
                        ? (int) round(($completions[$course->id] ?? 0) / ($memberCount * $lessons) * 100)
                        : 0,
                ];
            });

        return Inertia::render('workspaces/courses/index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'courses' => $courses,
            'stats' => $canManage
                ? $this->workspaceStats($workspace, $lessonTotals, $completions, $memberCount)
                : $this->learnerStats($request, $workspace, $startedIds, $lessonTotals),
            'leaderboard' => $this->completionLeaderboard($workspace, array_sum($lessonTotals)),
            'openCreateOnMount' => $request->boolean('new'),
        ]);
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
     * @return array<string, mixed>
     */
    private function workspaceStats(Workspace $workspace, array $lessonTotals, array $completions, int $memberCount): array
    {
        $total = Course::ofWorkspace($workspace)->count();
        $published = Course::ofWorkspace($workspace)->where('status', 'published')->count();
        $lessons = array_sum($lessonTotals);

        return [
            'can_manage' => true,
            'total_courses' => $total,
            'draft_courses' => $total - $published,
            'active_courses' => $published,
            'total_lessons' => $lessons,
            'my_courses' => 0,
            'completed_lessons' => 0,
            'my_completion' => 0,
            'avg_completion' => $lessons > 0
                ? (int) round(array_sum($completions) / ($memberCount * $lessons) * 100)
                : 0,
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

    /**
     * Sign an upload for a course cover. Scoped to the workspace rather than a
     * course: the create form needs a key before the course exists.
     */
    public function presignCover(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCourses->value, $workspace);

        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'starts_with:image/'],
        ]);

        $disk = Storage::disk(config('filesystems.course_media_disk'));

        $extension = Str::lower(pathinfo($validated['file_name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $key = $this->pendingCoverPrefix($workspace).Str::uuid().'.'.$extension;

        try {
            $signed = $disk->temporaryUploadUrl(
                $key,
                Carbon::now()->addMinutes(60),
                ['ContentType' => $validated['content_type']],
            );
        } catch (RuntimeException) {
            // A disk that cannot sign an upload. The caller falls back to
            // posting the file through the app.
            return response()->json(['supported' => false]);
        }

        return response()->json([
            'supported' => true,
            'key' => $key,
            'url' => $signed['url'],
            'headers' => $signed['headers'],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCourses->value, $workspace);

        $validated = $request->validate($this->rules());

        $course = Course::create([
            ...collect($validated)->except(['cover_image', 'cover_image_key', 'remove_cover_image'])->all(),
            'workspace_id' => $workspace->id,
        ]);

        $this->attachCoverImage($request, $course);

        return redirect()
            ->route('workspaces.courses.index', $workspace)
            ->with('success', "Course \"{$course->name}\" created.");
    }

    public function show(Request $request, Workspace $workspace, Course $course): Response
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $course->load(['media', 'modules.lessons.media']);

        return Inertia::render('workspaces/courses/show', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'progress' => $this->presentProgress($request, $course),
            'team' => $this->presentTeam($workspace, $course),
            'course' => [
                ...$this->present($course),
                'modules' => $this->presentModules($course),
            ],
        ]);
    }

    /**
     * The player: the course's lessons beside a video pane. Read-only — all the
     * editing lives on the detail page.
     */
    public function preview(Request $request, Workspace $workspace, Course $course): Response
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $course->load(['media', 'modules.lessons.media']);

        // Which lesson to open on. A stale or foreign id is ignored rather than
        // erroring — the page falls back to the first playable lesson.
        $requested = $request->integer('lesson')
            ?: CourseEnrollment::where('course_id', $course->id)
                ->where('user_id', $request->user()->getKey())
                ->value('last_lesson_id');

        $known = $course->modules
            ->flatMap->lessons
            ->pluck('id')
            ->contains($requested);

        return Inertia::render('workspaces/courses/preview', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'initialLessonId' => $known ? $requested : null,
            'progress' => $this->presentProgress($request, $course),
            'course' => [
                ...$this->present($course),
                'modules' => $this->presentModules($course),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, Course $course)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate($this->rules());

        $course->update(collect($validated)->except(['cover_image', 'cover_image_key', 'remove_cover_image'])->all());

        $this->attachCoverImage($request, $course);

        return back()->with('success', "Course \"{$course->name}\" updated.");
    }

    public function destroy(Request $request, Workspace $workspace, Course $course)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::DeleteCourses->value, $workspace);

        $name = $course->name;
        $course->delete();

        return redirect()
            ->route('workspaces.courses.index', $workspace)
            ->with('success', "Course \"{$name}\" deleted.");
    }

    /**
     * Serve a course's cover image. The bucket is private, so this either
     * hands out a short-lived signed URL or streams the bytes when the disk
     * cannot sign one (a local disk in development) — the file is never
     * publicly readable.
     */
    public function showMedia(Request $request, Workspace $workspace, Course $course, Media $media)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        // Route-model binding resolves the media row by id alone, so without
        // this any member could pull another course's file by guessing an id.
        abort_unless(
            $media->model_type === $course->getMorphClass() && $media->model_id === $course->getKey(),
            404,
        );

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(5),
            ));
        }

        return $disk->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    /**
     * Shared between store and update so the two can't drift on what they
     * will accept. Mime enforcement lives here rather than on the model's
     * collection: a validation failure is a field error the user can act on,
     * where media-library's own check throws a 500.
     *
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published'],
            // `heif` alongside `heic` because iOS photos are routinely
            // detected as the former.
            // A key from presignCover: the usual path, where the browser has
            // already put the file in the bucket. `cover_image` is the fallback
            // for disks that cannot sign an upload.
            'cover_image_key' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
            // Set when the user clears the cover without picking a replacement.
            'remove_cover_image' => ['nullable', 'boolean'],
        ];
    }

    private function attachCoverImage(Request $request, Course $course): void
    {
        $key = $request->string('cover_image_key')->toString();

        if ($key !== '') {
            $this->adoptCoverImage($course, $key);

            return;
        }

        if ($request->hasFile('cover_image')) {
            // The collection is singleFile(), so this replaces any existing
            // cover and deletes the old object from the bucket.
            $course->addMediaFromRequest('cover_image')
                ->toMediaCollection(Course::COVER_IMAGE_COLLECTION);

            return;
        }

        // Clearing without picking a replacement. Checked only when no file
        // was sent, so a user who clears and then chooses a new image in the
        // same edit still ends up with the new one.
        if ($request->boolean('remove_cover_image')) {
            $course->clearMediaCollection(Course::COVER_IMAGE_COLLECTION);
        }
    }

    /**
     * Adopt a cover the browser already uploaded, moving it into the collection
     * with a bucket-side copy.
     *
     * Deliberately not addMediaFromDisk(): that streams the object down and
     * uploads it back, which defeats the point of uploading straight to the
     * bucket. See CourseLessonController@attachVideo for the same reasoning.
     */
    private function adoptCoverImage(Course $course, string $key): void
    {
        // Without this a member could point the course at any object in the
        // bucket. The prefix is workspace-scoped because a course being created
        // has no id yet.
        abort_unless(
            str_starts_with($key, $this->pendingCoverPrefix($course->workspace)),
            403,
            'That upload does not belong to this workspace.',
        );

        $diskName = config('filesystems.course_media_disk');
        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($key), 422, 'The upload could not be found. Please try again.');

        $course->clearMediaCollection(Course::COVER_IMAGE_COLLECTION);

        $fileName = Str::limit(basename(str_replace('\\', '/', $key)), 180, '');

        $media = $course->media()->create([
            'collection_name' => Course::COVER_IMAGE_COLLECTION,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $disk->mimeType($key) ?: 'image/jpeg',
            'disk' => $diskName,
            'conversions_disk' => $diskName,
            'size' => $disk->size($key),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            // Normally stamped by the FileAdder, which is bypassed here.
            'uuid' => (string) Str::uuid(),
        ]);

        $disk->copy($key, PathGeneratorFactory::create($media)->getPath($media).$fileName);
        $disk->delete($key);
    }

    /**
     * Where a workspace's not-yet-adopted cover uploads live.
     */
    private function pendingCoverPrefix(Workspace $workspace): string
    {
        return "pending/course-covers/{$workspace->id}/";
    }

    /**
     * The raw media rows carry disk paths and custom properties the page has
     * no use for, so they don't get shipped to the browser. No URL is
     * serialized either — the page links to the media route, which signs one
     * on demand.
     *
     * @return array<string, mixed>
     */
    private function present(Course $course): array
    {
        $cover = $course->coverImage();

        return [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'category' => $course->category,
            'status' => $course->status,
            'modules_count' => $course->modules_count,
            'created_at' => $course->created_at?->toIso8601String(),
            'updated_at' => $course->updated_at?->toIso8601String(),
            'cover_image' => $cover ? [
                'id' => $cover->id,
                'file_name' => $cover->file_name,
                'mime_type' => $cover->mime_type,
                'size' => $cover->size,
            ] : null,
        ];
    }

    /**
     * The current user's own run through a course: whether they started it,
     * which lessons they have finished, and where to drop them back in.
     *
     * @return array<string, mixed>
     */
    private function presentProgress(Request $request, Course $course): array
    {
        $userId = $request->user()->getKey();

        $lessonIds = $course->modules->flatMap->lessons->pluck('id');

        $completed = CourseLessonCompletion::where('user_id', $userId)
            ->whereIn('course_lesson_id', $lessonIds)
            ->pluck('course_lesson_id')
            ->all();

        $enrollment = CourseEnrollment::where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        $total = $lessonIds->count();

        return [
            'started' => $enrollment !== null,
            'started_at' => $enrollment?->started_at?->toIso8601String(),
            'completed_at' => $enrollment?->completed_at?->toIso8601String(),
            'resume_lesson_id' => $enrollment?->last_lesson_id,
            'completed_lesson_ids' => $completed,
            'completed_count' => count($completed),
            'total_lessons' => $total,
            'percent' => $total > 0 ? (int) round(count($completed) / $total * 100) : 0,
        ];
    }

    /**
     * How far each learner has got through this one course.
     *
     * Only people who have actually started it are listed. Members who never
     * opened the course would otherwise fill the panel with 0% rows that say
     * nothing about how the people taking it are doing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentTeam(Workspace $workspace, Course $course): array
    {
        $lessonIds = $course->modules->flatMap->lessons->pluck('id');
        $total = $lessonIds->count();

        if ($total === 0) {
            return [];
        }

        $done = DB::table('course_lesson_completions')
            ->whereIn('course_lesson_id', $lessonIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as aggregate')
            ->pluck('aggregate', 'user_id');

        // Enrollment is the record of having started, so someone who pressed
        // Start but finished nothing still belongs here at 0%.
        return DB::table('course_enrollments')
            ->join('users', 'course_enrollments.user_id', '=', 'users.id')
            ->where('course_enrollments.course_id', $course->id)
            ->get(['users.id', 'users.name'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'percent' => (int) round((($done[$user->id] ?? 0) / $total) * 100),
            ])
            ->sortByDesc('percent')
            ->values()
            ->all();
    }

    /**
     * A course's structure for the detail and player pages. No media URL is
     * serialized: both link to the video route, which signs one on demand.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentModules(Course $course): array
    {
        return $course->modules->map(fn ($module) => [
            'id' => $module->id,
            'name' => $module->name,
            'position' => $module->position,
            'lessons' => $module->lessons->map(function ($lesson) {
                $video = $lesson->video();

                return [
                    'id' => $lesson->id,
                    'name' => $lesson->name,
                    'position' => $lesson->position,
                    'duration_seconds' => $lesson->duration_seconds,
                    'video' => $video ? [
                        'id' => $video->id,
                        'file_name' => $video->file_name,
                        'size' => $video->size,
                    ] : null,
                ];
            })->all(),
        ])->all();
    }

    /**
     * Courses are only reachable by members of a workspace that has the module
     * switched on. Owners bypass the permission check (they hold '*'), so the
     * module flag has to be enforced here. There is no workspace global scope,
     * so a bound course is re-checked against the workspace by hand.
     */
    private function guard(Request $request, Workspace $workspace, ?Course $course = null): void
    {
        abort_unless($workspace->courses_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        if ($course && $course->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
