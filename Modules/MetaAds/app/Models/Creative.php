<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creative extends Model
{
    protected $table = 'meta_ads_creatives';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'object_story_spec' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'meta_ads_creative_id');
    }
}
