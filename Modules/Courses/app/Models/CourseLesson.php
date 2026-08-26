<?php

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CourseLesson extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The lesson's video.
     */
    public const VIDEO_COLLECTION = 'video';

    protected $fillable = [
        'course_module_id',
        'name',
        'position',
        'duration_seconds',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::VIDEO_COLLECTION)
            // One video per lesson: uploading again replaces the old file
            // rather than leaving orphans in the bucket.
            ->singleFile()
            // No acceptsMimeTypes() here on purpose. What a lesson video may be
            // is enforced by request validation, which returns a field error
            // the user can act on; media-library re-sniffs the mime from the
            // stored bytes and throws, turning a truncated upload into a 500.
            //
            // The disk is pinned explicitly: media-library's own `disk_name`
            // falls back to filesystems.default, which is "local" here, so a
            // collection that omits this silently never reaches the bucket.
            ->useDisk(config('filesystems.course_media_disk'));
    }

    public function video(): ?Media
    {
        return $this->getFirstMedia(static::VIDEO_COLLECTION);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(CourseLessonCompletion::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }
}
