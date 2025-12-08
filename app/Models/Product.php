<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'workspace_id',
        'owner_id',
        'title',
        'name',
        'code',
        'category',
        'ad_budget_today',
        'status',
        'description',
    ];

    protected $casts = [
        'ad_budget_today' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->where('collection_name', 'PRODUCT_IMAGE');
    }

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
