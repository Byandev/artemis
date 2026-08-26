<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\CourseModule;

function structureCourse(): array
{
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $course = Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Structured Course',
        'status' => 'draft',
    ]);

    return ['workspace' => $workspace, 'course' => $course];
}

it('adds modules to a course and appends each one last', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $base = "/workspaces/{$workspace->slug}/courses/{$course->id}";

    foreach (['Intro', 'Deep Dive'] as $name) {
        $this->post("{$base}/modules", ['name' => $name])->assertRedirect();
    }

    expect($course->modules()->pluck('name')->all())->toBe(['Intro', 'Deep Dive'])
        ->and($course->modules()->pluck('position')->all())->toBe([1, 2]);
});

it('creates a module with a placeholder name so it can be named in place', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();

    // The UI appends the row first, then edits it, so it posts nothing.
    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}/modules")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($course->modules()->sole()->name)->toBe('Untitled module');
});

it('creates a lesson with a placeholder name so it can be named in place', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);

    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($module->lessons()->sole()->name)->toBe('Untitled lesson');
});

it('still requires a name when renaming', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);

    $this->put("/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}", ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('adds lessons under a module and appends each one last', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $base = "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}";

    foreach (['Lesson A', 'Lesson B'] as $name) {
        $this->post("{$base}/lessons", ['name' => $name])->assertRedirect();
    }

    expect($module->lessons()->pluck('name')->all())->toBe(['Lesson A', 'Lesson B'])
        ->and($module->lessons()->pluck('position')->all())->toBe([1, 2]);
});

it('renames a module and a lesson', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Old Module', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Old Lesson', 'position' => 1]);
    $base = "/workspaces/{$workspace->slug}/courses/{$course->id}";

    $this->put("{$base}/modules/{$module->id}", ['name' => 'New Module'])->assertRedirect();
    $this->put("{$base}/modules/{$module->id}/lessons/{$lesson->id}", ['name' => 'New Lesson'])
        ->assertRedirect();

    expect($module->fresh()->name)->toBe('New Module')
        ->and($lesson->fresh()->name)->toBe('New Lesson');
});

it('cascades lessons when a module is deleted', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Doomed', 'position' => 1]);
    $module->lessons()->create(['name' => 'Also doomed', 'position' => 1]);

    $this->delete("/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}")
        ->assertRedirect();

    expect(CourseModule::find($module->id))->toBeNull()
        ->and(CourseLesson::where('course_module_id', $module->id)->count())->toBe(0);
});

it('cascades modules and lessons when the course is deleted', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    $this->delete("/workspaces/{$workspace->slug}/courses/{$course->id}")->assertRedirect();

    expect(CourseModule::where('course_id', $course->id)->count())->toBe(0)
        ->and(CourseLesson::where('course_module_id', $module->id)->count())->toBe(0);
});

it('404s a module that belongs to another course', function () {
    ['workspace' => $workspace, 'course' => $mine] = structureCourse();

    $theirs = Course::create([
        'workspace_id' => $workspace->id,
        'name' => 'Other Course',
        'status' => 'draft',
    ]);
    $module = $theirs->modules()->create(['name' => 'Theirs', 'position' => 1]);

    // Binding resolves the module by id alone, so the guard has to catch this.
    $this->put("/workspaces/{$workspace->slug}/courses/{$mine->id}/modules/{$module->id}", [
        'name' => 'Hijacked',
    ])->assertNotFound();
});

it('404s a lesson that belongs to another module', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $mine = $course->modules()->create(['name' => 'Mine', 'position' => 1]);
    $other = $course->modules()->create(['name' => 'Other', 'position' => 2]);
    $lesson = $other->lessons()->create(['name' => 'Theirs', 'position' => 1]);

    $this->put(
        "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$mine->id}/lessons/{$lesson->id}",
        ['name' => 'Hijacked'],
    )->assertNotFound();
});

it('does not let a non-member touch a course\'s structure', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();

    $this->actingAs(User::factory()->create())
        ->post("/workspaces/{$workspace->slug}/courses/{$course->id}/modules", ['name' => 'Nope'])
        ->assertForbidden();
});

it('404s structure endpoints when the courses module is off', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $workspace->update(['courses_module_enabled' => false]);

    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}/modules", ['name' => 'Nope'])
        ->assertNotFound();
});

it('uploads a lesson video to the configured course media disk', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    $this->post(
        "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video",
        ['video' => UploadedFile::fake()->create('lesson.mp4', 2048, 'video/mp4')],
    )->assertRedirect()->assertSessionHasNoErrors();

    $media = $lesson->fresh()->video();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('s3')
        ->and($media->collection_name)->toBe(CourseLesson::VIDEO_COLLECTION);
});

it('accepts a lesson video with no size cap', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    // 600 MB — far past the 10 MB cap the cover image carries.
    $this->post(
        "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video",
        ['video' => UploadedFile::fake()->create('big.mp4', 614400, 'video/mp4')],
    )->assertRedirect()->assertSessionHasNoErrors();

    expect($lesson->fresh()->video())->not->toBeNull();
});

it('rejects a lesson upload that is not a video', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    $this->post(
        "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video",
        ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
    )->assertSessionHasErrors('video');
});

it('replaces a lesson video rather than accumulating files', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);
    $url = "/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video";

    foreach (['first.mp4', 'second.mp4'] as $name) {
        $this->post($url, [
            'video' => UploadedFile::fake()->create($name, 512, 'video/mp4'),
        ])->assertRedirect();
    }

    expect($lesson->fresh()->getMedia(CourseLesson::VIDEO_COLLECTION))->toHaveCount(1)
        ->and($lesson->fresh()->video()->file_name)->toBe('second.mp4');
});

it('404s playback when the lesson has no video', function () {
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    $this->get("/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video")
        ->assertNotFound();
});

it('removes a lesson video', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);
    $lesson->addMedia(UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4'))
        ->toMediaCollection(CourseLesson::VIDEO_COLLECTION);

    $this->delete("/workspaces/{$workspace->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/video")
        ->assertRedirect();

    expect($lesson->fresh()->video())->toBeNull();
});

/** A lesson plus the base URL of its video endpoints. */
function lessonWithVideoUrl(): array
{
    ['workspace' => $workspace, 'course' => $course] = structureCourse();
    $module = $course->modules()->create(['name' => 'Intro', 'position' => 1]);
    $lesson = $module->lessons()->create(['name' => 'Lesson', 'position' => 1]);

    return [
        'lesson' => $lesson,
        'url' => "/workspaces/{$workspace->slug}/courses/{$course->id}"
            ."/modules/{$module->id}/lessons/{$lesson->id}/video",
    ];
}

it('signs an upload url namespaced to the lesson', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $response = $this->postJson("{$url}/presign", [
        'file_name' => 'lecture.mp4',
        'content_type' => 'video/mp4',
    ])->assertOk();

    expect($response->json('key'))
        ->toStartWith("pending/lesson-videos/{$lesson->id}/")
        ->toEndWith('.mp4');
});

it('refuses to presign something that is not a video', function () {
    ['url' => $url] = lessonWithVideoUrl();

    $this->postJson("{$url}/presign", [
        'file_name' => 'notes.pdf',
        'content_type' => 'application/pdf',
    ])->assertStatus(422);
});

it('adopts an uploaded object into the lesson video collection', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, 'fake-bytes');

    $this->post("{$url}/attach", ['key' => $key, 'file_name' => 'lecture.mp4'])
        ->assertRedirect();

    $media = $lesson->fresh()->video();

    expect($media?->file_name)->toBe('lecture.mp4');

    // The row is worthless if the object isn't where it points: assert the
    // hand-built media record and the copy target actually agree.
    expect(Storage::disk('s3')->exists($media->getPathRelativeToRoot()))->toBeTrue();

    // The object is moved out of the pending prefix, not left behind.
    expect(Storage::disk('s3')->exists($key))->toBeFalse();

    // uuid is normally stamped by the FileAdder, which this path bypasses.
    expect($media->uuid)->not->toBeNull();
});

it('deletes the stored object when the lesson video is removed', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, 'fake-bytes');
    $this->post("{$url}/attach", ['key' => $key, 'file_name' => 'lecture.mp4']);

    $path = $lesson->fresh()->video()->getPathRelativeToRoot();

    $this->delete($url)->assertRedirect();

    expect(Storage::disk('s3')->exists($path))->toBeFalse();
});

it('adopts a video larger than media-library\'s default 10 MB ceiling', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    // 16 MB — past the package default that threw FileIsTooBig before
    // config/media-library.php raised max_file_size.
    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, str_repeat('x', 16 * 1024 * 1024));

    $this->post("{$url}/attach", ['key' => $key, 'file_name' => 'big.mp4'])
        ->assertRedirect();

    expect($lesson->fresh()->video()?->file_name)->toBe('big.mp4');
});

it('stores the duration the browser read off the video', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, 'fake-bytes');

    $this->post("{$url}/attach", [
        'key' => $key,
        'file_name' => 'lecture.mp4',
        'duration_seconds' => 580,
    ])->assertRedirect();

    // 9:40 in the outline.
    expect($lesson->fresh()->duration_seconds)->toBe(580);
});

it('accepts a video whose duration the browser could not read', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, 'fake-bytes');

    $this->post("{$url}/attach", ['key' => $key, 'file_name' => 'lecture.mp4'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($lesson->fresh()->duration_seconds)->toBeNull()
        ->and($lesson->fresh()->video())->not->toBeNull();
});

it('clears the duration when the video is removed', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $key = "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4';
    Storage::disk('s3')->put($key, 'fake-bytes');
    $this->post("{$url}/attach", [
        'key' => $key,
        'file_name' => 'lecture.mp4',
        'duration_seconds' => 580,
    ]);

    $this->delete($url)->assertRedirect();

    // A stale duration next to no video would show a length for nothing.
    expect($lesson->fresh()->duration_seconds)->toBeNull();
});

it('refuses to adopt an object belonging to another lesson', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    // A key minted for a different lesson must not be attachable here.
    $foreign = 'pending/lesson-videos/'.($lesson->id + 999).'/'.Str::uuid().'.mp4';
    Storage::disk('s3')->put($foreign, 'fake-bytes');

    $this->post("{$url}/attach", ['key' => $foreign, 'file_name' => 'stolen.mp4'])
        ->assertForbidden();

    expect($lesson->fresh()->video())->toBeNull();
});

it('rejects an attach whose object is not in the bucket', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['lesson' => $lesson, 'url' => $url] = lessonWithVideoUrl();

    $this->post("{$url}/attach", [
        'key' => "pending/lesson-videos/{$lesson->id}/".Str::uuid().'.mp4',
        'file_name' => 'missing.mp4',
    ])->assertStatus(422);
});

it('signs an upload on a local disk too, so dev matches production', function () {
    config(['filesystems.course_media_disk' => 'local']);

    ['url' => $url] = lessonWithVideoUrl();

    // Laravel 12 signs local uploads through its own /storage route, so the
    // presigned path is exercised in development as well and the direct-post
    // fallback is reserved for drivers that genuinely cannot sign.
    $this->postJson("{$url}/presign", [
        'file_name' => 'lecture.mp4',
        'content_type' => 'video/mp4',
    ])->assertOk()->assertJson(['supported' => true]);
});
