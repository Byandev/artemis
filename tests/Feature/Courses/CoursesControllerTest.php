<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Courses\Models\Course;

function makeCourse($workspace, array $attributes = []): Course
{
    return Course::create(array_merge([
        'workspace_id' => $workspace->id,
        'name' => 'Test Course',
        'status' => 'draft',
    ], $attributes));
}

it('defaults the courses module to off for a new workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    expect($workspace->fresh()->courses_module_enabled)->toBeFalse();
});

it('404s the index when the courses module is off', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->get("/workspaces/{$workspace->slug}/courses")->assertNotFound();
});

it('lists courses for an owner when the module is on', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    makeCourse($workspace, ['name' => 'Onboarding 101', 'category' => 'Sales']);

    $this->get("/workspaces/{$workspace->slug}/courses")->assertOk();
});

it('does not let a non-member reach a workspace\'s courses', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get("/workspaces/{$workspace->slug}/courses")
        ->assertForbidden();
});

it('stores a course with its name, description, category, and status', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->post("/workspaces/{$workspace->slug}/courses", [
        'name' => 'Sales Basics',
        'description' => 'How we sell.',
        'category' => 'Sales',
        'status' => 'published',
    ])->assertRedirect("/workspaces/{$workspace->slug}/courses");

    $course = Course::where('workspace_id', $workspace->id)->sole();

    expect($course->name)->toBe('Sales Basics')
        ->and($course->description)->toBe('How we sell.')
        ->and($course->category)->toBe('Sales')
        ->and($course->status)->toBe('published');
});

it('requires a name and a valid status', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->post("/workspaces/{$workspace->slug}/courses", [
        'name' => '',
        'status' => 'archived',
    ])->assertSessionHasErrors(['name', 'status']);
});

it('leaves description and category optional', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->post("/workspaces/{$workspace->slug}/courses", [
        'name' => 'Bare Minimum',
        'status' => 'draft',
    ])->assertSessionHasNoErrors();

    $course = Course::where('workspace_id', $workspace->id)->sole();

    expect($course->description)->toBeNull()
        ->and($course->category)->toBeNull();
});

it('attaches a cover image to the configured course media disk', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->post("/workspaces/{$workspace->slug}/courses", [
        'name' => 'With Cover',
        'status' => 'draft',
        'cover_image' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertRedirect();

    $media = Course::where('workspace_id', $workspace->id)->sole()->coverImage();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('s3')
        ->and($media->collection_name)->toBe(Course::COVER_IMAGE_COLLECTION);
});

it('replaces the cover image rather than accumulating files', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);

    foreach (['first.jpg', 'second.jpg'] as $file) {
        $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}", [
            '_method' => 'put',
            'name' => $course->name,
            'status' => 'draft',
            'cover_image' => UploadedFile::fake()->image($file),
        ])->assertRedirect();
    }

    // The collection is singleFile(), so the first upload is gone.
    expect($course->fresh()->getMedia(Course::COVER_IMAGE_COLLECTION))->toHaveCount(1)
        ->and($course->fresh()->coverImage()->file_name)->toBe('second.jpg');
});

it('removes an existing cover image when the clear flag is set', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);
    $course->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->toMediaCollection(Course::COVER_IMAGE_COLLECTION);

    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}", [
        '_method' => 'put',
        'name' => $course->name,
        'status' => 'draft',
        'remove_cover_image' => '1',
    ])->assertRedirect();

    expect($course->fresh()->coverImage())->toBeNull();
});

it('keeps the new upload when a cover is cleared and replaced at once', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);
    $course->addMedia(UploadedFile::fake()->image('old.jpg'))
        ->toMediaCollection(Course::COVER_IMAGE_COLLECTION);

    // The flag is only honoured when no replacement file came with it.
    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}", [
        '_method' => 'put',
        'name' => $course->name,
        'status' => 'draft',
        'remove_cover_image' => '1',
        'cover_image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertRedirect();

    expect($course->fresh()->coverImage()?->file_name)->toBe('new.jpg');
});

it('leaves an existing cover alone when the flag is absent', function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);
    $course->addMedia(UploadedFile::fake()->image('keep.jpg'))
        ->toMediaCollection(Course::COVER_IMAGE_COLLECTION);

    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}", [
        '_method' => 'put',
        'name' => 'Renamed',
        'status' => 'draft',
    ])->assertRedirect();

    expect($course->fresh()->coverImage()?->file_name)->toBe('keep.jpg');
});

it('rejects a cover image that is not an accepted image type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);

    $this->post("/workspaces/{$workspace->slug}/courses", [
        'name' => 'Bad Cover',
        'status' => 'draft',
        'cover_image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('cover_image');
});

it('404s a course belonging to another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    $mine->update(['courses_module_enabled' => true]);

    ['workspace' => $theirs] = makeWorkspaceWithOwner();
    $other = makeCourse($theirs);

    // Route-model binding resolves by id alone, so the guard has to catch this.
    $this->get("/workspaces/{$mine->slug}/courses/{$other->id}")->assertNotFound();
});

it('updates a course over POST with a spoofed PUT', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace, ['name' => 'Old Name']);

    // The dialog submits multipart, so an update rides `_method` on a POST.
    $this->post("/workspaces/{$workspace->slug}/courses/{$course->id}", [
        '_method' => 'put',
        'name' => 'New Name',
        'category' => 'Onboarding',
        'status' => 'published',
    ])->assertRedirect();

    $fresh = $course->fresh();

    expect($fresh->name)->toBe('New Name')
        ->and($fresh->category)->toBe('Onboarding')
        ->and($fresh->status)->toBe('published');
});

it('has no standalone create or edit pages', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);

    // Both forms are modals on the index/detail pages.
    $this->get("/workspaces/{$workspace->slug}/courses/{$course->id}/edit")
        ->assertNotFound();
});

it('deletes a course', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['courses_module_enabled' => true]);
    $course = makeCourse($workspace);

    $this->delete("/workspaces/{$workspace->slug}/courses/{$course->id}")
        ->assertRedirect("/workspaces/{$workspace->slug}/courses");

    expect(Course::find($course->id))->toBeNull();
});
