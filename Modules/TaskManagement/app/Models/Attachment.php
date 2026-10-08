<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;

/**
 * A file stored against a task.
 *
 * The media library's own model, extended for two reasons: an upload has to say
 * who added it, which the package has no column for; and authorization here
 * resolves to a Space role, which needs a policy and a `visibleTo` scope like
 * every other model in the module has.
 *
 * Unlike Matrix, this is not the app-wide `media-library.media_model` -- Artemis
 * stores plenty of other media. Task opts in through `getMediaModel()`.
 *
 * @property int|null $uploaded_by
 */
class Attachment extends Media implements BelongsToTaskWorkspace
{
    /**
     * The content types a browser may render inline, on this application's own
     * origin.
     *
     * Raster images and PDFs only. `image/svg+xml` is deliberately absent: an
     * SVG is a document that can carry `<script>`, so rendering one here would
     * run it as this application, against the viewer's own session. Anything
     * outside this list is handed over as a download instead.
     *
     * @var list<string>
     */
    public const PREVIEWABLE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/heic',
        'image/heif',
        'application/pdf',
    ];

    /**
     * The package observes only the configured media model, and Eloquent events
     * are keyed by class, so a subclass has to be observed itself -- otherwise
     * deleting an attachment would leave its file on the disk.
     */
    protected static function booted(): void
    {
        static::observe(MediaObserver::class);
    }

    public function taskWorkspaceId(): ?int
    {
        $model = $this->model;

        return $model instanceof Task ? $model->workspace_id : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether a browser may show this file instead of downloading it.
     *
     * Answered from the type the server detected from the bytes when the file
     * was stored, never from its name -- so an HTML page saved as `photo.jpg`
     * is stored as `text/html` and is not previewable, which is the whole point
     * of asking the content rather than the extension.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    /**
     * Limit the query to files on tasks inside spaces the given user can see.
     *
     * @param  Builder<Attachment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('model_type', (new Task)->getMorphClass())
            ->whereIn('model_id', Task::query()->visibleTo($user)->select('id'));
    }

    /**
     * Delete the files of the given tasks, taking them off the disk with them.
     *
     * `tasks.parent_id` and `tasks.task_list_id` both cascade in the database,
     * so deleting a task or its list removes descendant rows without any
     * Eloquent event firing -- and the media library hangs its disk cleanup off
     * that event. Anything that deletes tasks therefore purges their files
     * first, or the bucket keeps objects no row points at any more.
     *
     * @param  Builder<Task>  $tasks
     */
    public static function purgeForTasks(Builder $tasks): void
    {
        self::query()
            ->where('model_type', (new Task)->getMorphClass())
            ->whereIn('model_id', $tasks->clone()->select('id'))
            ->each(fn (self $attachment) => $attachment->delete());
    }
}
