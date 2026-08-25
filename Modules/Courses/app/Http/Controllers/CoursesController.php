<?php

namespace Modules\Courses\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Courses\Models\Course;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CoursesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $courses = Course::ofWorkspace($workspace)
            // `media` is eager loaded so the cover column doesn't fire a query
            // per row on a full page of courses.
            ->with('media')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Course $course) => $this->present($course));

        return Inertia::render('workspaces/courses/index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'courses' => $courses,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCourses->value, $workspace);

        $validated = $request->validate($this->rules());

        $course = Course::create([
            ...collect($validated)->except(['cover_image', 'remove_cover_image'])->all(),
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

        $course->load('media');

        return Inertia::render('workspaces/courses/show', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'course' => $this->present($course),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Course $course)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate($this->rules());

        $course->update(collect($validated)->except(['cover_image', 'remove_cover_image'])->all());

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
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
            // Set when the user clears the cover without picking a replacement.
            'remove_cover_image' => ['nullable', 'boolean'],
        ];
    }

    private function attachCoverImage(Request $request, Course $course): void
    {
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
