<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.course_media_disk' => 's3']);
});

/** Write an object and backdate it so the age cutoff can be exercised. */
function pendingUpload(string $path, int $hoursOld = 0): void
{
    Storage::disk('s3')->put($path, str_repeat('x', 1024));

    if ($hoursOld > 0) {
        touch(
            Storage::disk('s3')->path($path),
            now()->subHours($hoursOld)->getTimestamp(),
        );
    }
}

it('deletes pending uploads older than the cutoff', function () {
    pendingUpload('pending/lesson-videos/1/old.mp4', hoursOld: 48);

    $this->artisan('courses:prune-pending-uploads')->assertSuccessful();

    expect(Storage::disk('s3')->exists('pending/lesson-videos/1/old.mp4'))->toBeFalse();
});

it('leaves an upload that may still be in flight alone', function () {
    pendingUpload('pending/lesson-videos/1/fresh.mp4');

    $this->artisan('courses:prune-pending-uploads')->assertSuccessful();

    expect(Storage::disk('s3')->exists('pending/lesson-videos/1/fresh.mp4'))->toBeTrue();
});

it('honours a custom age cutoff', function () {
    pendingUpload('pending/lesson-videos/1/twohours.mp4', hoursOld: 2);

    $this->artisan('courses:prune-pending-uploads --hours=1')->assertSuccessful();

    expect(Storage::disk('s3')->exists('pending/lesson-videos/1/twohours.mp4'))->toBeFalse();
});

it('never touches attached media outside the pending prefix', function () {
    pendingUpload('1/lecture.mp4', hoursOld: 500);
    pendingUpload('media/2/cover.jpg', hoursOld: 500);
    pendingUpload('pending/lesson-videos/1/orphan.mp4', hoursOld: 500);

    $this->artisan('courses:prune-pending-uploads')->assertSuccessful();

    // Only the pending prefix is in scope; a stored file is never "old".
    expect(Storage::disk('s3')->exists('1/lecture.mp4'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('media/2/cover.jpg'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('pending/lesson-videos/1/orphan.mp4'))->toBeFalse();
});

it('prunes abandoned course covers as well as lesson videos', function () {
    pendingUpload('pending/course-covers/7/orphan.jpg', hoursOld: 48);
    pendingUpload('pending/lesson-videos/1/orphan.mp4', hoursOld: 48);

    $this->artisan('courses:prune-pending-uploads')->assertSuccessful();

    expect(Storage::disk('s3')->exists('pending/course-covers/7/orphan.jpg'))->toBeFalse()
        ->and(Storage::disk('s3')->exists('pending/lesson-videos/1/orphan.mp4'))->toBeFalse();
});

it('deletes nothing on a dry run', function () {
    pendingUpload('pending/lesson-videos/1/old.mp4', hoursOld: 48);

    $this->artisan('courses:prune-pending-uploads --dry-run')
        ->expectsOutputToContain('would delete pending/lesson-videos/1/old.mp4')
        ->assertSuccessful();

    expect(Storage::disk('s3')->exists('pending/lesson-videos/1/old.mp4'))->toBeTrue();
});

it('succeeds when there is nothing to prune', function () {
    $this->artisan('courses:prune-pending-uploads')->assertSuccessful();
});
