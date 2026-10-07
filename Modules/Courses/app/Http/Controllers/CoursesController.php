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
use Modules\Courses\Http\Controllers\Concerns\PresentsCourses;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseEnrollment;
use Modules\Courses\Models\CourseLessonCompletion;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class CoursesController extends Controller
{
    use AuthorizesRequests;
    use PresentsCourses;

    /**
     * The page shell only. The grid, the stat tiles and the leaderboard each
     * load over XHR from Api\CourseCatalogController, so the page paints
     * before the aggregates are counted.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $canManage = $request->user()->hasPermission(Permission::EditCourses->value, $workspace);

        return Inertia::render('workspaces/courses/index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'openCreateOnMount' => $request->boolean('new'),
            // Read off the URL so a shared or reloaded link opens with the same
            // filters applied; the page takes it from there.
            'filters' => [
                'search' => trim((string) $request->string('search')),
                'categories' => array_values(array_filter((array) $request->input('categories', []))),
                'page' => max(1, $request->integer('page', 1)),
            ],
            'categoryOptions' => $this->categoryOptions($workspace, $canManage),
        ]);
    }

    /**
     * Categories actually in use, so the filter never offers an option that
     * would return nothing. Scoped to what the viewer can see.
     *
     * @return array<int, string>
     */
    private function categoryOptions(Workspace $workspace, bool $canManage): array
    {
        return Course::ofWorkspace($workspace)
            ->unless($canManage, fn ($q) => $q->where('status', 'published'))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
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
