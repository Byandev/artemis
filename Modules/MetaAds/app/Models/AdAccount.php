<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdAccount extends Model
{
    protected $table = 'meta_ads_accounts';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function graphAccountId(): string
    {
        return 'act_'.$this->id;
    }

    public function metaUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meta_ads_user_account', 'meta_ads_account_id', 'meta_ads_user_id')
            ->withPivot('permitted_tasks')
            ->withTimestamps();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'meta_ads_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class, 'meta_ads_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'meta_ads_account_id');
    }

    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->whereHas('metaUsers.workspaces', function ($q) use ($workspace) {
            $q->where('workspaces.id', $workspace->id);
        });
    }
}
