<?php

namespace Modules\Creatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CreativeReview extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** A voice message recorded in the browser, alongside or instead of text. */
    public const VOICE_COLLECTION = 'voice';

    protected $table = 'creatives_reviews';

    protected $guarded = [];

    protected $casts = [
        'timestamp_seconds' => 'float',
        'region' => 'array',
    ];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::VOICE_COLLECTION)
            ->singleFile()
            ->useDisk(config('filesystems.creative_media_disk'));
    }

    public function voice(): ?Media
    {
        return $this->getFirstMedia(static::VOICE_COLLECTION);
    }
}
