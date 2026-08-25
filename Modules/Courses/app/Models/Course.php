<?php

namespace Modules\Courses\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Course extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The course's cover image.
     */
    public const COVER_IMAGE_COLLECTION = 'cover_image';

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'category',
        'status',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::COVER_IMAGE_COLLECTION)
            // One cover per course: uploading again replaces the old file
            // rather than leaving orphans in the bucket.
            ->singleFile()
            // No acceptsMimeTypes() here on purpose. What a cover may be is
            // enforced by request validation, which returns a field error the
            // user can act on; media-library re-sniffs the mime from the
            // stored bytes and throws, turning a truncated upload into a 500.
            //
            // The disk is pinned explicitly: media-library's own `disk_name`
            // falls back to filesystems.default, which is "local" here, so a
            // collection that omits this silently never reaches the bucket.
            ->useDisk(config('filesystems.course_media_disk'));
    }

    public function coverImage(): ?Media
    {
        return $this->getFirstMedia(static::COVER_IMAGE_COLLECTION);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
