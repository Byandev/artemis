<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A fund request's answer to one of the attachment requirements its transaction
 * type calls for: the uploaded file, held in the `file` media collection.
 */
class FundRequestAttachment extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const FILE_COLLECTION = 'file';

    protected $table = 'finance_fund_request_attachments';

    protected $fillable = [
        'fund_request_id',
        'attachment_requirement_id',
    ];

    protected $casts = [
        'fund_request_id' => 'integer',
        'attachment_requirement_id' => 'integer',
    ];

    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class, 'fund_request_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(FundRequestAttachmentRequirement::class, 'attachment_requirement_id');
    }

    public function registerMediaCollections(): void
    {
        // No acceptsMimeTypes() on purpose — see WorkspaceChecklistCompletion:
        // request validation owns what is accepted and returns a field error.
        $this->addMediaCollection(static::FILE_COLLECTION)
            ->useDisk(config('filesystems.fund_request_attachment_disk'))
            ->singleFile();
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(static::FILE_COLLECTION);
    }
}
