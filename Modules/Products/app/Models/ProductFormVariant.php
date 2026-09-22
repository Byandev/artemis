<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One size or variant of a product form — "30ml" under Oil — together with the
 * picture that size ships in.
 */
class ProductFormVariant extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const IMAGE_COLLECTION = 'PRODUCT_FORM_VARIANT_IMAGE';

    protected $fillable = [
        'product_form_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::IMAGE_COLLECTION)
            // One picture per size: uploading again replaces the old file
            // rather than leaving orphans in the bucket.
            ->singleFile()
            // No acceptsMimeTypes() here on purpose — what an image may be is
            // enforced by request validation, which returns a field error the
            // user can act on, where media-library's own check throws a 500.
            //
            // The disk is pinned explicitly: media-library's `disk_name` falls
            // back to filesystems.default, which is "local" here, so a
            // collection that omits this silently never reaches the bucket.
            ->useDisk(config('filesystems.product_form_media_disk'));
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ProductForm::class, 'product_form_id');
    }

    public function image(): ?Media
    {
        return $this->getFirstMedia(static::IMAGE_COLLECTION);
    }
}
