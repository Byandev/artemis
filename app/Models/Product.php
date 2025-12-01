<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    public function image(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->where('collection_name', 'PRODUCT_IMAGE');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
