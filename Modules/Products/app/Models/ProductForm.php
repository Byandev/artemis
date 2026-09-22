<?php

namespace Modules\Products\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery format a product can take — Oil, Spray, Patch, Capsule. Used when
 * categorizing the catalog. Each form carries the sizes/variants it ships in,
 * and every one of those carries its own picture.
 */
class ProductForm extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductFormVariant::class)->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
