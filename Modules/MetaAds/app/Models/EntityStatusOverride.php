<?php

namespace Modules\MetaAds\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityStatusOverride extends Model
{
    protected $table = 'meta_ads_entity_status_overrides';

    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
