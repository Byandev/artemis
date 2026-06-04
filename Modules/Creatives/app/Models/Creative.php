<?php

namespace Modules\Creatives\Models;

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Creative extends Model
{
    protected $guarded = [];

    protected $casts = [
        'creative_date' => 'date',
        'creator_id' => 'integer',
        'product_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignedReviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'creative_reviewers', 'creative_id', 'user_id')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CreativeReview::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(CreativeReview::class)->latestOfMany();
    }
}
