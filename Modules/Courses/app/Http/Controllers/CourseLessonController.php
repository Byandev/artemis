<?php

namespace Modules\Courses\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\CourseModule;
use RuntimeException;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Lessons are edited from the course detail page, under their module. Like
 * modules they carry no permissions of their own.
 */
class CourseLessonController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Workspace $workspace, Course $course, CourseModule $module)
    {
        $this->guard($request, $workspace, $course, $module);
        $this->authorize(Permission::CreateCourses->value, $workspace);

        // Optional on create, same as modules: the row is added first and
        // named in place.
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $module->lessons()->create([
            'name' => ($validated['name'] ?? null) ?: 'Untitled lesson',
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return back()->with('success', 'Lesson added.');
    }

    public function update(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $lesson->update($validated);

        return back()->with('success', 'Lesson updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::DeleteCourses->value, $workspace);

        $lesson->delete();

        return back()->with('success', 'Lesson deleted.');
    }

    /**
     * Hand the browser a short-lived URL it can PUT the video straight to S3
     * with. The bytes never pass through nginx or PHP, so neither
     * client_max_body_size nor upload_max_filesize applies to a lesson video.
     *
     * The key is minted here rather than accepted from the client, and it is
     * namespaced by lesson, so `attachVideo` can prove the object it is being
     * asked to adopt was one this endpoint issued for this lesson.
     */
    public function presignVideo(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'starts_with:video/'],
        ]);

        $disk = Storage::disk(config('filesystems.course_media_disk'));

        $extension = Str::lower(pathinfo($validated['file_name'], PATHINFO_EXTENSION)) ?: 'mp4';
        $key = $this->pendingPrefix($lesson).Str::uuid().'.'.$extension;

        try {
            $signed = $disk->temporaryUploadUrl(
                $key,
                Carbon::now()->addMinutes(60),
                ['ContentType' => $validated['content_type']],
            );
        } catch (RuntimeException) {
            // A disk that cannot sign an upload — a local disk in development
            // is the usual case. The caller falls back to posting the file
            // through the app. Checked by calling rather than by inspecting
            // the class: FilesystemAdapter always declares the method and
            // throws only when the underlying driver has no support.
            return response()->json(['supported' => false]);
        }

        return response()->json([
            'supported' => true,
            'key' => $key,
            'url' => $signed['url'],
            'headers' => $signed['headers'],
        ]);
    }

    /**
     * Adopt an object the browser already uploaded, moving it out of the
     * pending prefix into the collection's own path with a bucket-side copy.
     * Nothing is downloaded or re-uploaded, so this costs the same regardless
     * of how large the video is.
     */
    public function attachVideo(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate([
            'key' => ['required', 'string'],
            'file_name' => ['required', 'string', 'max:255'],
            // Read off the file by the browser; the app never sees the bytes.
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        // Without this a member could point the lesson at any object in the
        // bucket, including another workspace's media.
        abort_unless(
            str_starts_with($validated['key'], $this->pendingPrefix($lesson)),
            403,
            'That upload does not belong to this lesson.',
        );

        $diskName = config('filesystems.course_media_disk');
        $disk = Storage::disk($diskName);

        abort_unless(
            $disk->exists($validated['key']),
            422,
            'The upload could not be found. Please try again.',
        );

        // Deliberately NOT addMediaFromDisk(): that streams the object down to
        // a temp file and uploads it back, so a large video crosses the network
        // twice through PHP and times out — which is exactly what uploading
        // straight to the bucket was meant to avoid. The media row is built by
        // hand instead so the object can be moved with a server-side copy.
        $lesson->clearMediaCollection(CourseLesson::VIDEO_COLLECTION);

        $fileName = $this->safeFileName($validated['file_name']);

        $media = $lesson->media()->create([
            'collection_name' => CourseLesson::VIDEO_COLLECTION,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $disk->mimeType($validated['key']) ?: 'video/mp4',
            'disk' => $diskName,
            'conversions_disk' => $diskName,
            'size' => $disk->size($validated['key']),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            // Normally stamped by the FileAdder, which is bypassed here.
            'uuid' => (string) Str::uuid(),
        ]);

        $target = PathGeneratorFactory::create($media)->getPath($media).$fileName;

        // On S3 this is a CopyObject: the bytes never leave the bucket, so it
        // costs the same whether the video is 10 MB or 10 GB.
        $disk->copy($validated['key'], $target);
        $disk->delete($validated['key']);

        $lesson->update(['duration_seconds' => $validated['duration_seconds'] ?? null]);

        return back()->with('success', "Video uploaded to \"{$lesson->name}\".");
    }

    /**
     * Keep the browser's file name but strip anything that could escape the
     * collection's own directory.
     */
    private function safeFileName(string $name): string
    {
        return Str::limit(basename(str_replace('\\', '/', $name)), 180, '');
    }

    /**
     * Where a lesson's not-yet-adopted uploads live.
     */
    private function pendingPrefix(CourseLesson $lesson): string
    {
        return "pending/lesson-videos/{$lesson->id}/";
    }

    /**
     * Attach or replace a lesson's video by posting it through the app. This
     * is the fallback for disks that cannot sign an upload (a local disk in
     * development); the presigned path above is what production uses, and it
     * is the one with no size ceiling.
     */
    public function storeVideo(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate([
            // Deliberately no `max:` — lesson videos are not size capped here.
            // PHP's own upload_max_filesize / post_max_size still apply and are
            // what a very large upload will hit first.
            'video' => ['required', 'file', 'mimetypes:video/*'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        // The collection is singleFile(), so this replaces any existing video
        // and deletes the old object from the bucket.
        $lesson->addMediaFromRequest('video')
            ->toMediaCollection(CourseLesson::VIDEO_COLLECTION);

        $lesson->update(['duration_seconds' => $validated['duration_seconds'] ?? null]);

        return back()->with('success', "Video uploaded to \"{$lesson->name}\".");
    }

    public function destroyVideo(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $lesson->clearMediaCollection(CourseLesson::VIDEO_COLLECTION);
        $lesson->update(['duration_seconds' => null]);

        return back()->with('success', 'Video removed.');
    }

    /**
     * Stream a lesson's video. The bucket is private, so this redirects to a
     * short-lived signed URL — which also lets the browser range-request the
     * file straight from S3 rather than proxying it through PHP.
     */
    public function showVideo(Request $request, Workspace $workspace, Course $course, CourseModule $module, CourseLesson $lesson)
    {
        $this->guard($request, $workspace, $course, $module, $lesson);
        $this->authorize(Permission::ViewCourses->value, $workspace);

        $media = $lesson->video();

        abort_unless($media, 404, 'No video on file for this lesson.');

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(30),
            ));
        }

        return $disk->response($media->getPathRelativeToRoot());
    }

    /**
     * Every link in the chain is re-checked: route-model binding resolves each
     * of course, module, and lesson by id alone.
     */
    private function guard(
        Request $request,
        Workspace $workspace,
        Course $course,
        CourseModule $module,
        ?CourseLesson $lesson = null,
    ): void {
        abort_unless($workspace->courses_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        abort_unless($course->workspace_id === $workspace->id, 404);
        abort_unless($module->course_id === $course->id, 404);

        if ($lesson) {
            abort_unless($lesson->course_module_id === $module->id, 404);
        }
    }
}
